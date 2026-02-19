vcl 4.1;

# Production Varnish Configuration for Reverse Tender
# Optimized for high-traffic production environments with CDN integration

import std;
import directors;

# Production backend definitions with health checks
backend gateway_prod {
    .host = "gateway-service";
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

backend auth_prod {
    .host = "auth-service";
    .port = "8080";
    .connect_timeout = 2s;
    .first_byte_timeout = 10s;
    .between_bytes_timeout = 2s;
    .max_connections = 50;
    .probe = {
        .url = "/health";
        .interval = 10s;
        .timeout = 3s;
        .window = 5;
        .threshold = 3;
    };
}

backend user_prod {
    .host = "user-service";
    .port = "8080";
    .connect_timeout = 2s;
    .first_byte_timeout = 10s;
    .between_bytes_timeout = 2s;
    .max_connections = 50;
    .probe = {
        .url = "/health";
        .interval = 10s;
        .timeout = 3s;
        .window = 5;
        .threshold = 3;
    };
}

backend auction_prod {
    .host = "auction-service";
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

backend bidding_prod {
    .host = "bidding-service";
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

# Production ACL for cache purging and admin access
acl purge_acl {
    "localhost";
    "127.0.0.1";
    "10.0.0.0"/8;
    "172.16.0.0"/12;
    "192.168.0.0"/16;
    # Add your CDN provider IPs here
    "23.235.32.0"/20;  # Fastly IP range example
}

# Production request handling
sub vcl_recv {
    # Remove port from host header for consistent caching
    set req.http.Host = regsub(req.http.Host, ":[0-9]+", "");
    
    # Handle PURGE requests for cache invalidation
    if (req.method == "PURGE") {
        if (!client.ip ~ purge_acl) {
            return (synth(405, "Purging not allowed"));
        }
        return (purge);
    }
    
    # Handle BAN requests for pattern-based cache invalidation
    if (req.method == "BAN") {
        if (!client.ip ~ purge_acl) {
            return (synth(405, "Banning not allowed"));
        }
        ban("req.url ~ " + req.url);
        return (synth(200, "Banned"));
    }
    
    # Only allow GET, HEAD, PUT, POST, TRACE, OPTIONS, DELETE methods
    if (req.method != "GET" &&
        req.method != "HEAD" &&
        req.method != "PUT" &&
        req.method != "POST" &&
        req.method != "TRACE" &&
        req.method != "OPTIONS" &&
        req.method != "DELETE") {
        return (pipe);
    }
    
    # Production routing with intelligent backend selection
    if (req.url ~ "^/api/auth/") {
        set req.backend_hint = auth_prod;
        set req.http.X-Service = "auth";
    } elsif (req.url ~ "^/api/users/") {
        set req.backend_hint = user_prod;
        set req.http.X-Service = "user";
    } elsif (req.url ~ "^/api/auctions/") {
        set req.backend_hint = auction_prod;
        set req.http.X-Service = "auction";
    } elsif (req.url ~ "^/api/bids/") {
        set req.backend_hint = bidding_prod;
        set req.http.X-Service = "bidding";
    } else {
        set req.backend_hint = gateway_prod;
        set req.http.X-Service = "gateway";
    }
    
    # Production caching rules
    if (req.method != "GET" && req.method != "HEAD") {
        # Don't cache non-GET/HEAD requests
        return (pass);
    }
    
    # Don't cache requests with authentication
    if (req.http.Authorization || req.http.Cookie ~ "auth_token|session_id") {
        return (pass);
    }
    
    # Cache static assets aggressively
    if (req.url ~ "\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$") {
        unset req.http.Cookie;
        return (hash);
    }
    
    # Cache API responses with query string normalization
    if (req.url ~ "^/api/(auctions|users|categories)") {
        # Normalize query parameters for better cache hit ratio
        set req.url = std.querysort(req.url);
        return (hash);
    }
    
    # Don't cache admin or real-time endpoints
    if (req.url ~ "^/api/(admin|websocket|notifications|live)") {
        return (pass);
    }
    
    return (hash);
}

# Production backend response handling
sub vcl_backend_response {
    # Set cache tags for selective purging
    if (bereq.http.X-Service) {
        set beresp.http.X-Cache-Tags = bereq.http.X-Service;
    }
    
    # Production caching policies
    if (bereq.url ~ "^/api/auctions") {
        set beresp.ttl = 600s;  # 10 minutes for auction data
        set beresp.grace = 3600s; # 1 hour grace period
        set beresp.http.Cache-Control = "public, max-age=600";
        set beresp.http.X-Cache-Type = "api-auction";
    } elsif (bereq.url ~ "^/api/users") {
        set beresp.ttl = 900s;  # 15 minutes for user data
        set beresp.grace = 3600s;
        set beresp.http.Cache-Control = "public, max-age=900";
        set beresp.http.X-Cache-Type = "api-user";
    } elsif (bereq.url ~ "^/api/categories") {
        set beresp.ttl = 1800s; # 30 minutes for categories
        set beresp.grace = 7200s; # 2 hours grace
        set beresp.http.Cache-Control = "public, max-age=1800";
        set beresp.http.X-Cache-Type = "api-category";
    }
    
    # Cache static assets for 24 hours
    if (bereq.url ~ "\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$") {
        set beresp.ttl = 86400s; # 24 hours
        set beresp.grace = 86400s;
        set beresp.http.Cache-Control = "public, max-age=86400";
        set beresp.http.X-Cache-Type = "static";
    }
    
    # Don't cache error responses
    if (beresp.status >= 400) {
        set beresp.ttl = 0s;
        set beresp.grace = 0s;
        return (deliver);
    }
    
    # Enable compression for text content
    if (beresp.http.Content-Type ~ "text|application/json|application/javascript|application/xml") {
        set beresp.do_gzip = true;
    }
    
    # Remove backend server information for security
    unset beresp.http.Server;
    unset beresp.http.X-Powered-By;
    
    return (deliver);
}

# Production response delivery
sub vcl_deliver {
    # Add cache status headers for debugging
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }
    
    # Add cache age information
    set resp.http.X-Cache-Age = obj.age;
    
    # Add service information for monitoring
    if (req.http.X-Service) {
        set resp.http.X-Served-By = req.http.X-Service;
    }
    
    # Production security headers
    set resp.http.X-Frame-Options = "SAMEORIGIN";
    set resp.http.X-Content-Type-Options = "nosniff";
    set resp.http.X-XSS-Protection = "1; mode=block";
    
    # Remove internal headers in production
    unset resp.http.X-Cache-Tags;
    unset resp.http.Via;
    unset resp.http.X-Varnish;
    
    return (deliver);
}

# Production error handling
sub vcl_backend_error {
    # Serve stale content if backend is down
    if (beresp.status >= 500 && beresp.status < 600) {
        if (stale.exists) {
            return (deliver);
        }
    }
    
    # Custom error page for production
    set beresp.http.Content-Type = "application/json";
    synthetic({"{"error": "Service temporarily unavailable", "status": "} + beresp.status + {"", "timestamp": "} + now + {""}});
    return (deliver);
}

# Production synthetic response handling
sub vcl_synth {
    if (resp.status == 200 && resp.reason == "Banned") {
        set resp.http.Content-Type = "application/json";
        synthetic({"{"message": "Cache invalidated successfully", "timestamp": "} + now + {""}});
        return (deliver);
    }
    
    if (resp.status == 405) {
        set resp.http.Content-Type = "application/json";
        synthetic({"{"error": "Method not allowed", "status": 405, "timestamp": "} + now + {""}});
        return (deliver);
    }
    
    return (deliver);
}

# Production hash generation for consistent caching
sub vcl_hash {
    hash_data(req.url);
    hash_data(req.http.Host);
    
    # Include service in hash for better cache distribution
    if (req.http.X-Service) {
        hash_data(req.http.X-Service);
    }
    
    return (lookup);
}
