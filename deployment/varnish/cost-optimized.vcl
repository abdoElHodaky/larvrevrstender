vcl 4.1;

# Cost-Optimized Varnish Configuration for Reverse Tender
# Designed for maximum cache efficiency and minimal resource usage

import std;

# Cost-optimized backend configuration
backend gateway_cost {
    .host = "gateway-service";
    .port = "8080";
    .connect_timeout = 3s;
    .first_byte_timeout = 15s;
    .between_bytes_timeout = 3s;
    .max_connections = 50;  # Reduced for cost optimization
    .probe = {
        .url = "/health";
        .interval = 30s;  # Less frequent probes
        .timeout = 5s;
        .window = 3;
        .threshold = 2;
    };
}

backend auth_cost {
    .host = "auth-service";
    .port = "8080";
    .connect_timeout = 3s;
    .first_byte_timeout = 15s;
    .between_bytes_timeout = 3s;
    .max_connections = 25;
    .probe = {
        .url = "/health";
        .interval = 30s;
        .timeout = 5s;
        .window = 3;
        .threshold = 2;
    };
}

backend user_cost {
    .host = "user-service";
    .port = "8080";
    .connect_timeout = 3s;
    .first_byte_timeout = 15s;
    .between_bytes_timeout = 3s;
    .max_connections = 25;
    .probe = {
        .url = "/health";
        .interval = 30s;
        .timeout = 5s;
        .window = 3;
        .threshold = 2;
    };
}

backend auction_cost {
    .host = "auction-service";
    .port = "8080";
    .connect_timeout = 3s;
    .first_byte_timeout = 15s;
    .between_bytes_timeout = 3s;
    .max_connections = 50;
    .probe = {
        .url = "/health";
        .interval = 30s;
        .timeout = 5s;
        .window = 3;
        .threshold = 2;
    };
}

# ACL for CDN and admin access
acl cdn_acl {
    "localhost";
    "127.0.0.1";
    "10.0.0.0"/8;
    "172.16.0.0"/12;
    "192.168.0.0"/16;
    # KeyCDN IP ranges
    "103.16.24.0"/22;
    "103.18.24.0"/22;
    "103.31.200.0"/22;
}

# Cost-optimized request handling
sub vcl_recv {
    # Normalize host header
    set req.http.Host = regsub(req.http.Host, ":[0-9]+", "");
    
    # Handle PURGE requests from CDN
    if (req.method == "PURGE") {
        if (!client.ip ~ cdn_acl) {
            return (synth(405, "Purging not allowed"));
        }
        return (purge);
    }
    
    # Aggressive caching for cost optimization
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }
    
    # Route to appropriate backend
    if (req.url ~ "^/api/auth/") {
        set req.backend_hint = auth_cost;
        set req.http.X-Service = "auth";
    } elsif (req.url ~ "^/api/users/") {
        set req.backend_hint = user_cost;
        set req.http.X-Service = "user";
    } elsif (req.url ~ "^/api/auctions/") {
        set req.backend_hint = auction_cost;
        set req.http.X-Service = "auction";
    } else {
        set req.backend_hint = gateway_cost;
        set req.http.X-Service = "gateway";
    }
    
    # Aggressive static asset caching
    if (req.url ~ "\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$") {
        unset req.http.Cookie;
        unset req.http.Authorization;
        return (hash);
    }
    
    # Cache API responses aggressively for cost savings
    if (req.url ~ "^/api/(auctions|users|categories)") {
        # Remove tracking parameters
        set req.url = regsuball(req.url, "[?&](utm_[^&]*|fbclid|gclid)", "");
        set req.url = regsuball(req.url, "[?&]$", "");
        
        # Cache even with some cookies (cost optimization)
        if (req.http.Cookie !~ "auth_token|session_id") {
            unset req.http.Cookie;
        }
        
        return (hash);
    }
    
    # Don't cache authenticated requests
    if (req.http.Authorization || req.http.Cookie ~ "auth_token|session_id") {
        return (pass);
    }
    
    return (hash);
}

# Cost-optimized backend response handling
sub vcl_backend_response {
    # Set cache tags for CDN integration
    if (bereq.http.X-Service) {
        set beresp.http.X-Cache-Tags = bereq.http.X-Service;
    }
    
    # Aggressive caching policies for cost optimization
    if (bereq.url ~ "^/api/auctions") {
        set beresp.ttl = 900s;   # 15 minutes (longer for cost savings)
        set beresp.grace = 7200s; # 2 hours grace
        set beresp.http.Cache-Control = "public, max-age=900";
    } elsif (bereq.url ~ "^/api/users") {
        set beresp.ttl = 1200s;  # 20 minutes
        set beresp.grace = 7200s;
        set beresp.http.Cache-Control = "public, max-age=1200";
    } elsif (bereq.url ~ "^/api/categories") {
        set beresp.ttl = 3600s;  # 1 hour (categories change rarely)
        set beresp.grace = 14400s; # 4 hours grace
        set beresp.http.Cache-Control = "public, max-age=3600";
    }
    
    # Very aggressive static asset caching
    if (bereq.url ~ "\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$") {
        set beresp.ttl = 604800s; # 7 days
        set beresp.grace = 604800s;
        set beresp.http.Cache-Control = "public, max-age=604800, immutable";
    }
    
    # Cache successful responses longer
    if (beresp.status == 200) {
        # Extend TTL for successful responses
        set beresp.ttl = beresp.ttl * 1.5;
    }
    
    # Don't cache errors but serve stale if available
    if (beresp.status >= 400) {
        set beresp.ttl = 0s;
        if (beresp.status >= 500 && beresp.status < 600) {
            set beresp.grace = 3600s; # Serve stale for server errors
        }
    }
    
    # Enable compression for bandwidth savings
    if (beresp.http.Content-Type ~ "text|application/json|application/javascript|application/xml") {
        set beresp.do_gzip = true;
    }
    
    # Remove backend server information
    unset beresp.http.Server;
    unset beresp.http.X-Powered-By;
    
    return (deliver);
}

# Cost-optimized response delivery
sub vcl_deliver {
    # Add cache status for monitoring
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }
    
    # Add cost optimization headers
    set resp.http.X-Cache-Age = obj.age;
    set resp.http.X-Cost-Optimized = "true";
    
    # Security headers
    set resp.http.X-Frame-Options = "SAMEORIGIN";
    set resp.http.X-Content-Type-Options = "nosniff";
    
    # Remove internal headers
    unset resp.http.X-Cache-Tags;
    unset resp.http.Via;
    unset resp.http.X-Varnish;
    
    return (deliver);
}

# Serve stale content on backend errors (cost optimization)
sub vcl_backend_error {
    if (beresp.status >= 500 && beresp.status < 600) {
        if (stale.exists) {
            return (deliver);
        }
    }
    
    # Minimal error response
    set beresp.http.Content-Type = "application/json";
    synthetic({"{"error": "Service unavailable", "status": "} + beresp.status + {""}});
    return (deliver);
}

# Cost-optimized hash generation
sub vcl_hash {
    hash_data(req.url);
    hash_data(req.http.Host);
    
    # Include service for better distribution
    if (req.http.X-Service) {
        hash_data(req.http.X-Service);
    }
    
    return (lookup);
}
