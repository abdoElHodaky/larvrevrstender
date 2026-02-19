vcl 4.1;

# Multi-Tier Varnish Configuration with Upstash Redis Fallback
# Varnish (L1) → Upstash Redis (L2) → Application

import std;
import directors;

# Backend configuration
backend app_server {
    .host = "127.0.0.1";
    .port = "8080";
    .connect_timeout = 2s;
    .first_byte_timeout = 10s;
    .between_bytes_timeout = 2s;
    .max_connections = 100;
    .probe = {
        .url = "/health";
        .interval = 10s;
        .timeout = 3s;
        .window = 5;
        .threshold = 3;
    };
}

# Upstash Redis backend for fallback caching
backend upstash_redis {
    .host = "region-redis.upstash.io";
    .port = "6380";
    .connect_timeout = 5s;
    .first_byte_timeout = 15s;
    .between_bytes_timeout = 5s;
    .max_connections = 50;
    .probe = {
        .url = "/ping";
        .interval = 30s;
        .timeout = 10s;
        .window = 3;
        .threshold = 2;
    };
}

# ACL for cache purging
acl purge {
    "localhost";
    "127.0.0.1";
    "10.0.0.0"/8;
    "172.16.0.0"/12;
    "192.168.0.0"/16;
}

# Initialize directors for load balancing
sub vcl_init {
    new app_director = directors.round_robin();
    app_director.add_backend(app_server);
}

sub vcl_recv {
    # Set backend director
    set req.backend_hint = app_director.backend();
    
    # Handle PURGE requests
    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return (synth(405, "Purging not allowed"));
        }
        return (purge);
    }
    
    # Handle BAN requests for cache invalidation
    if (req.method == "BAN") {
        if (!client.ip ~ purge) {
            return (synth(405, "Banning not allowed"));
        }
        ban("req.url ~ " + req.url);
        return (synth(200, "Banned"));
    }
    
    # Only cache GET and HEAD requests
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }
    
    # Remove tracking parameters
    if (req.url ~ "\?") {
        set req.url = regsuball(req.url, "[?&](utm_[^&]*|fbclid|gclid|_ga|_gid)", "");
        set req.url = regsuball(req.url, "[?&]$", "");
    }
    
    # Handle authentication
    if (req.http.Authorization || req.http.Cookie ~ "auth_token|session_id") {
        # Don't cache authenticated requests
        set req.http.X-Cache-Auth = "1";
        return (pass);
    }
    
    # API endpoint caching strategy
    if (req.url ~ "^/api/") {
        # Cache public API endpoints
        if (req.url ~ "^/api/(auctions|categories|users)") {
            # Add cache tags for selective purging
            set req.http.X-Cache-Tags = "api";
            if (req.url ~ "^/api/auctions") {
                set req.http.X-Cache-Tags = req.http.X-Cache-Tags + ",auctions";
            } elsif (req.url ~ "^/api/categories") {
                set req.http.X-Cache-Tags = req.http.X-Cache-Tags + ",categories";
            } elsif (req.url ~ "^/api/users") {
                set req.http.X-Cache-Tags = req.http.X-Cache-Tags + ",users";
            }
            return (hash);
        }
        
        # Don't cache private API endpoints
        if (req.url ~ "^/api/(auth|profile|bids)") {
            return (pass);
        }
    }
    
    # Static assets - always cache
    if (req.url ~ "\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot|pdf)$") {
        unset req.http.Cookie;
        set req.http.X-Cache-Tags = "static";
        return (hash);
    }
    
    # Default caching behavior
    return (hash);
}

sub vcl_backend_response {
    # Set cache tags from request
    if (bereq.http.X-Cache-Tags) {
        set beresp.http.X-Cache-Tags = bereq.http.X-Cache-Tags;
    }
    
    # Multi-tier caching strategy
    if (beresp.status == 200) {
        # API endpoints caching
        if (bereq.url ~ "^/api/auctions") {
            set beresp.ttl = 300s;      # 5 minutes in Varnish
            set beresp.grace = 1800s;   # 30 minutes grace
            set beresp.http.Cache-Control = "public, max-age=300";
            set beresp.http.X-Upstash-TTL = "900";  # 15 minutes in Upstash
        } elsif (bereq.url ~ "^/api/categories") {
            set beresp.ttl = 1800s;     # 30 minutes in Varnish
            set beresp.grace = 3600s;   # 1 hour grace
            set beresp.http.Cache-Control = "public, max-age=1800";
            set beresp.http.X-Upstash-TTL = "3600"; # 1 hour in Upstash
        } elsif (bereq.url ~ "^/api/users") {
            set beresp.ttl = 600s;      # 10 minutes in Varnish
            set beresp.grace = 1800s;   # 30 minutes grace
            set beresp.http.Cache-Control = "public, max-age=600";
            set beresp.http.X-Upstash-TTL = "1800"; # 30 minutes in Upstash
        }
        
        # Static assets - long caching
        if (bereq.url ~ "\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$") {
            set beresp.ttl = 86400s;    # 24 hours in Varnish
            set beresp.grace = 604800s; # 7 days grace
            set beresp.http.Cache-Control = "public, max-age=86400, immutable";
            set beresp.http.X-Upstash-TTL = "604800"; # 7 days in Upstash
        }
        
        # Enable compression for text content
        if (beresp.http.Content-Type ~ "text|application/json|application/javascript|application/xml") {
            set beresp.do_gzip = true;
        }
    }
    
    # Handle errors with grace period
    if (beresp.status >= 400) {
        if (beresp.status >= 500 && beresp.status < 600) {
            # Serve stale content for server errors
            set beresp.grace = 3600s;
            # Try Upstash fallback for server errors
            set beresp.http.X-Upstash-Fallback = "1";
        } else {
            # Don't cache client errors
            set beresp.ttl = 0s;
        }
    }
    
    # Add multi-tier cache headers
    set beresp.http.X-Cache-Tier = "varnish";
    set beresp.http.X-Cache-Backend = bereq.backend;
    
    # Remove backend server information
    unset beresp.http.Server;
    unset beresp.http.X-Powered-By;
    
    return (deliver);
}

sub vcl_deliver {
    # Add cache status headers
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
        set resp.http.X-Cache-Tier = "varnish-hit";
    } else {
        set resp.http.X-Cache = "MISS";
        set resp.http.X-Cache-Tier = "varnish-miss";
    }
    
    # Add cache age and TTL information
    set resp.http.X-Cache-Age = obj.age;
    set resp.http.X-Cache-TTL = obj.ttl;
    
    # Add Upstash fallback information
    if (resp.http.X-Upstash-TTL) {
        set resp.http.X-Upstash-Cache-TTL = resp.http.X-Upstash-TTL;
    }
    
    # Security headers
    set resp.http.X-Frame-Options = "SAMEORIGIN";
    set resp.http.X-Content-Type-Options = "nosniff";
    set resp.http.X-XSS-Protection = "1; mode=block";
    
    # CORS headers for API endpoints
    if (req.url ~ "^/api/") {
        set resp.http.Access-Control-Allow-Origin = "*";
        set resp.http.Access-Control-Allow-Methods = "GET, POST, PUT, DELETE, OPTIONS";
        set resp.http.Access-Control-Allow-Headers = "Content-Type, Authorization, X-Requested-With";
        set resp.http.Access-Control-Max-Age = "86400";
    }
    
    # Remove internal headers
    unset resp.http.X-Cache-Tags;
    unset resp.http.X-Upstash-TTL;
    unset resp.http.X-Upstash-Fallback;
    unset resp.http.Via;
    unset resp.http.X-Varnish;
    
    return (deliver);
}

sub vcl_backend_error {
    # Try Upstash Redis fallback on backend errors
    if (beresp.status >= 500 && beresp.status < 600) {
        # Check if we can serve from Upstash
        if (bereq.http.X-Upstash-Fallback != "1") {
            set bereq.http.X-Upstash-Fallback = "1";
            set bereq.backend_hint = upstash_redis;
            return (retry);
        }
        
        # If Upstash also fails, serve stale if available
        if (stale.exists) {
            return (deliver);
        }
    }
    
    # Custom error response
    set beresp.http.Content-Type = "application/json";
    set beresp.status = 503;
    synthetic({"{"error": "Service temporarily unavailable", "status": 503, "cache_tier": "varnish-error"}"});
    return (deliver);
}

sub vcl_hash {
    hash_data(req.url);
    hash_data(req.http.Host);
    
    # Include authentication status in hash
    if (req.http.X-Cache-Auth) {
        hash_data("authenticated");
    }
    
    # Include cache tags in hash for better distribution
    if (req.http.X-Cache-Tags) {
        hash_data(req.http.X-Cache-Tags);
    }
    
    return (lookup);
}

# Handle cache warming
sub vcl_hit {
    # Refresh cache if TTL is low and grace period is available
    if (obj.ttl < 60s && obj.grace > 0s) {
        # Serve stale content while refreshing in background
        set req.http.X-Cache-Refresh = "1";
        return (deliver);
    }
    
    return (deliver);
}

sub vcl_miss {
    # Add miss reason for debugging
    set req.http.X-Cache-Miss-Reason = "not-in-cache";
    return (fetch);
}

# Synthetic responses for special cases
sub vcl_synth {
    if (resp.status == 301 || resp.status == 302) {
        set resp.http.Location = resp.reason;
        set resp.reason = "Moved";
        return (deliver);
    }
    
    # Handle PURGE responses
    if (resp.status == 200 && req.method == "PURGE") {
        set resp.http.Content-Type = "application/json";
        synthetic({"{"status": "purged", "url": ""} + req.url + {"", "timestamp": ""} + now + {""}});
        return (deliver);
    }
    
    # Handle BAN responses
    if (resp.status == 200 && req.method == "BAN") {
        set resp.http.Content-Type = "application/json";
        synthetic({"{"status": "banned", "pattern": ""} + req.url + {"", "timestamp": ""} + now + {""}});
        return (deliver);
    }
    
    return (deliver);
}
