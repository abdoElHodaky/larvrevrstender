vcl 4.0;

# Fastly CDN Integration for Reverse Tender
# This VCL configuration optimizes Fastly for the Reverse Tender application

# Backend configuration - your Varnish server
backend reverse_tender_origin {
    .host = "your-varnish-server.com";  # Replace with your actual domain
    .port = "80";
    .connect_timeout = 5s;
    .first_byte_timeout = 30s;
    .between_bytes_timeout = 10s;
    .max_connections = 200;
    .probe = {
        .url = "/health";
        .interval = 30s;
        .timeout = 10s;
        .window = 5;
        .threshold = 3;
    };
}

# Fastly request handling
sub vcl_recv {
    # Set backend for all requests
    set req.backend = reverse_tender_origin;
    
    # Remove Fastly-specific headers that shouldn't reach origin
    unset req.http.Fastly-Client-IP;
    unset req.http.Fastly-FF;
    
    # Preserve original client IP
    if (req.http.Fastly-Client-IP) {
        set req.http.X-Forwarded-For = req.http.Fastly-Client-IP;
    }
    
    # Handle PURGE requests for real-time cache invalidation
    if (req.method == "PURGE") {
        # Only allow purging from authorized IPs
        if (!client.ip ~ purge_acl) {
            error 405 "Not allowed";
        }
        return (purge);
    }
    
    # Handle FASTLYPURGE for Fastly-specific purging
    if (req.method == "FASTLYPURGE") {
        return (purge);
    }
    
    # Normalize host header
    set req.http.Host = "your-domain.com";  # Replace with your actual domain
    
    # API request optimization
    if (req.url ~ "^/api/") {
        # Remove unnecessary query parameters for better cache hit ratio
        if (req.url ~ "^/api/(auctions|users|categories)") {
            # Keep important query parameters, remove tracking ones
            set req.url = regsuball(req.url, "[?&](utm_[^&]*|fbclid|gclid)", "");
            set req.url = regsuball(req.url, "[?&]$", "");
        }
        
        # Don't cache authenticated API requests
        if (req.http.Authorization || req.http.Cookie ~ "auth_token|session_id") {
            return (pass);
        }
    }
    
    # Static asset optimization
    if (req.url ~ "\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$") {
        # Remove all cookies for static assets
        unset req.http.Cookie;
        return (lookup);
    }
    
    # Don't cache real-time endpoints
    if (req.url ~ "^/api/(websocket|notifications|live|admin)") {
        return (pass);
    }
    
    return (lookup);
}

# Fastly backend response handling
sub vcl_fetch {
    # Set Fastly-specific caching headers
    
    # API endpoint caching
    if (bereq.url ~ "^/api/auctions") {
        set beresp.ttl = 300s;  # 5 minutes
        set beresp.grace = 1800s; # 30 minutes grace
        set beresp.http.Cache-Control = "public, max-age=300";
        
        # Enable stale-while-revalidate for better performance
        set beresp.http.Cache-Control = beresp.http.Cache-Control + ", stale-while-revalidate=1800";
    }
    
    if (bereq.url ~ "^/api/users") {
        set beresp.ttl = 600s;  # 10 minutes
        set beresp.grace = 3600s; # 1 hour grace
        set beresp.http.Cache-Control = "public, max-age=600, stale-while-revalidate=3600";
    }
    
    if (bereq.url ~ "^/api/categories") {
        set beresp.ttl = 1800s; # 30 minutes
        set beresp.grace = 7200s; # 2 hours grace
        set beresp.http.Cache-Control = "public, max-age=1800, stale-while-revalidate=7200";
    }
    
    # Static assets - cache for 24 hours
    if (bereq.url ~ "\.(css|js|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$") {
        set beresp.ttl = 86400s; # 24 hours
        set beresp.grace = 86400s;
        set beresp.http.Cache-Control = "public, max-age=86400, immutable";
    }
    
    # Don't cache error responses
    if (beresp.status >= 400) {
        set beresp.ttl = 0s;
        return (deliver);
    }
    
    # Enable compression for text content
    if (beresp.http.Content-Type ~ "text|application/json|application/javascript|application/xml") {
        set beresp.do_gzip = true;
    }
    
    # Set cache tags for selective purging
    if (bereq.url ~ "^/api/auctions") {
        set beresp.http.Surrogate-Key = "api auctions";
    } elsif (bereq.url ~ "^/api/users") {
        set beresp.http.Surrogate-Key = "api users";
    } elsif (bereq.url ~ "^/api/categories") {
        set beresp.http.Surrogate-Key = "api categories";
    }
    
    return (deliver);
}

# Fastly response delivery
sub vcl_deliver {
    # Add Fastly-specific headers
    set resp.http.X-Served-By = "Fastly";
    
    # Add cache status
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }
    
    # Add edge location information
    set resp.http.X-Fastly-Pop = server.datacenter;
    
    # Security headers
    set resp.http.X-Frame-Options = "SAMEORIGIN";
    set resp.http.X-Content-Type-Options = "nosniff";
    set resp.http.X-XSS-Protection = "1; mode=block";
    set resp.http.Strict-Transport-Security = "max-age=31536000; includeSubDomains";
    
    # Remove internal headers
    unset resp.http.Surrogate-Key;
    unset resp.http.X-Varnish;
    unset resp.http.Via;
    
    return (deliver);
}

# Fastly error handling
sub vcl_error {
    # Custom error pages
    if (obj.status == 503) {
        set obj.http.Content-Type = "application/json";
        synthetic {"{"error": "Service temporarily unavailable", "status": 503, "message": "Please try again in a few moments"}"};
        return (deliver);
    }
    
    if (obj.status == 404) {
        set obj.http.Content-Type = "application/json";
        synthetic {"{"error": "Not found", "status": 404, "message": "The requested resource was not found"}"};
        return (deliver);
    }
    
    return (deliver);
}

# ACL for purge requests
acl purge_acl {
    "localhost";
    "127.0.0.1";
    "your-server-ip";  # Replace with your server IP
    # Add your office/admin IPs here
}

# Fastly-specific subroutines for advanced features

# Real-time analytics
sub vcl_log {
    # Log important metrics for analytics
    log "cache_status:" + if(obj.hits > 0, "HIT", "MISS");
    log "response_time:" + time.elapsed.msec;
    log "backend_response_time:" + beresp.backend.response.time;
    log "url:" + req.url;
    log "user_agent:" + req.http.User-Agent;
}

# Edge computing for dynamic content
sub vcl_recv {
    # Add geolocation data
    set req.http.X-Country-Code = geoip.country_code;
    set req.http.X-City = geoip.city;
    
    # Device detection
    if (req.http.User-Agent ~ "Mobile|Android|iPhone") {
        set req.http.X-Device-Type = "mobile";
    } elsif (req.http.User-Agent ~ "Tablet|iPad") {
        set req.http.X-Device-Type = "tablet";
    } else {
        set req.http.X-Device-Type = "desktop";
    }
}

# A/B testing support
sub vcl_recv {
    # Simple A/B testing based on client IP
    if (std.random(0, 100) < 50) {
        set req.http.X-AB-Test = "A";
    } else {
        set req.http.X-AB-Test = "B";
    }
}

# Rate limiting for API endpoints
sub vcl_recv {
    # Basic rate limiting for API endpoints
    if (req.url ~ "^/api/" && req.method == "POST") {
        # This would typically integrate with Fastly's rate limiting service
        # For now, we'll pass it through
    }
}
