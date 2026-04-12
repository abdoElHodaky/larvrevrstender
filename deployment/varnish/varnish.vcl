vcl 4.1;

import directors;
import std;

# Backend definitions for all services
backend gateway_service {
    .host = "gateway-service";
    .port = "8080";
    .probe = {
        .url = "/health";
        .timeout = 5s;
        .interval = 10s;
        .window = 5;
        .threshold = 3;
    }
}

backend auth_service {
    .host = "auth-service";
    .port = "8080";
    .probe = {
        .url = "/health";
        .timeout = 5s;
        .interval = 10s;
        .window = 5;
        .threshold = 3;
    }
}

backend user_service {
    .host = "user-service";
    .port = "8080";
    .probe = {
        .url = "/health";
        .timeout = 5s;
        .interval = 10s;
        .window = 5;
        .threshold = 3;
    }
}

backend auction_service {
    .host = "auction-service";
    .port = "8080";
    .probe = {
        .url = "/health";
        .timeout = 5s;
        .interval = 10s;
        .window = 5;
        .threshold = 3;
    }
}

backend bidding_service {
    .host = "bidding-service";
    .port = "8080";
    .probe = {
        .url = "/health";
        .timeout = 5s;
        .interval = 10s;
        .window = 5;
        .threshold = 3;
    }
}

backend order_service {
    .host = "order-service";
    .port = "8080";
    .probe = {
        .url = "/health";
        .timeout = 5s;
        .interval = 10s;
        .window = 5;
        .window = 5;
        .threshold = 3;
    }
}

backend payment_service {
    .host = "payment-service";
    .port = "8080";
    .probe = {
        .url = "/health";
        .timeout = 5s;
        .interval = 10s;
        .window = 5;
        .threshold = 3;
    }
}

backend notification_service {
    .host = "notification-service";
    .port = "8080";
    .probe = {
        .url = "/health";
        .timeout = 5s;
        .interval = 10s;
        .window = 5;
        .threshold = 3;
    }
}

backend analytics_service {
    .host = "analytics-service";
    .port = "8080";
    .probe = {
        .url = "/health";
        .timeout = 5s;
        .interval = 10s;
        .window = 5;
        .threshold = 3;
    }
}

backend vin_ocr_service {
    .host = "vin-ocr-service";
    .port = "8080";
    .probe = {
        .url = "/health";
        .timeout = 5s;
        .interval = 10s;
        .window = 5;
        .threshold = 3;
    }
}

# Load balancer for gateway service (main entry point)
sub vcl_init {
    new gateway_director = directors.round_robin();
    gateway_director.add_backend(gateway_service);
}

# Access Control Lists
acl purge {
    "localhost";
    "127.0.0.1";
    "::1";
    "10.0.0.0"/8;
    "172.16.0.0"/12;
    "192.168.0.0"/16;
}

sub vcl_recv {
    # Remove port from host header
    set req.http.Host = regsub(req.http.Host, ":[0-9]+", "");
    
    # Handle purge requests
    if (req.method == "PURGE") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed"));
        }
        return (purge);
    }
    
    # Handle BAN requests for cache invalidation
    if (req.method == "BAN") {
        if (!client.ip ~ purge) {
            return (synth(405, "Not allowed"));
        }
        ban("req.url ~ " + req.url);
        return (synth(200, "Banned"));
    }
    
    # Route requests based on URL path
    if (req.url ~ "^/api/auth/") {
        set req.backend_hint = auth_service;
    } else if (req.url ~ "^/api/users/") {
        set req.backend_hint = user_service;
    } else if (req.url ~ "^/api/auctions/") {
        set req.backend_hint = auction_service;
    } else if (req.url ~ "^/api/bids/") {
        set req.backend_hint = bidding_service;
    } else if (req.url ~ "^/api/orders/") {
        set req.backend_hint = order_service;
    } else if (req.url ~ "^/api/payments/") {
        set req.backend_hint = payment_service;
    } else if (req.url ~ "^/api/notifications/") {
        set req.backend_hint = notification_service;
    } else if (req.url ~ "^/api/analytics/") {
        set req.backend_hint = analytics_service;
    } else if (req.url ~ "^/api/vin-ocr/") {
        set req.backend_hint = vin_ocr_service;
    } else {
        # Default to gateway service
        set req.backend_hint = gateway_director.backend();
    }
    
    # Handle different HTTP methods
    if (req.method != "GET" &&
        req.method != "HEAD" &&
        req.method != "PUT" &&
        req.method != "POST" &&
        req.method != "TRACE" &&
        req.method != "OPTIONS" &&
        req.method != "DELETE" &&
        req.method != "PATCH") {
        return (pipe);
    }
    
    # Don't cache POST, PUT, DELETE, PATCH requests
    if (req.method != "GET" && req.method != "HEAD") {
        return (pass);
    }
    
    # Don't cache requests with authorization headers
    if (req.http.Authorization) {
        return (pass);
    }
    
    # Don't cache admin or private areas
    if (req.url ~ "^/admin" || req.url ~ "^/private") {
        return (pass);
    }
    
    # Don't cache API endpoints that modify data
    if (req.url ~ "^/api/.*/create" ||
        req.url ~ "^/api/.*/update" ||
        req.url ~ "^/api/.*/delete" ||
        req.url ~ "^/api/.*/store") {
        return (pass);
    }
    
    # Cache static assets for longer
    if (req.url ~ "\.(css|js|png|gif|jp(e)?g|swf|ico|pdf|flv|txt|xml)$") {
        unset req.http.Cookie;
        return (hash);
    }
    
    # Remove tracking parameters
    if (req.url ~ "(\?|&)(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=") {
        set req.url = regsuball(req.url, "&(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=([A-z0-9_\-\.%25]+)", "");
        set req.url = regsuball(req.url, "\?(utm_source|utm_medium|utm_campaign|utm_content|gclid|cx|ie|cof|siteurl)=([A-z0-9_\-\.%25]+)", "?");
        set req.url = regsub(req.url, "\?&", "?");
        set req.url = regsub(req.url, "\?$", "");
    }
    
    # Remove cookies for static content
    if (req.url ~ "\.(css|js|png|gif|jp(e)?g|swf|ico|pdf|flv|txt|xml)$") {
        unset req.http.Cookie;
    }
    
    return (hash);
}

sub vcl_backend_response {
    # Set cache headers based on content type
    if (beresp.http.Content-Type ~ "text/html") {
        set beresp.ttl = 5m;
        set beresp.grace = 1h;
    } else if (beresp.http.Content-Type ~ "application/json") {
        set beresp.ttl = 2m;
        set beresp.grace = 30m;
    } else if (beresp.http.Content-Type ~ "(css|js)") {
        set beresp.ttl = 1h;
        set beresp.grace = 6h;
    } else if (beresp.http.Content-Type ~ "(png|gif|jp(e)?g|swf|ico|pdf|flv)") {
        set beresp.ttl = 24h;
        set beresp.grace = 48h;
    }
    
    # Don't cache error responses
    if (beresp.status >= 400) {
        set beresp.ttl = 0s;
        set beresp.grace = 0s;
        return (deliver);
    }
    
    # Don't cache responses with Set-Cookie header
    if (beresp.http.Set-Cookie) {
        set beresp.ttl = 0s;
        set beresp.grace = 0s;
        return (deliver);
    }
    
    # Enable ESI for dynamic content
    if (beresp.http.Content-Type ~ "text/html") {
        set beresp.do_esi = true;
    }
    
    # Compress responses
    if (beresp.http.Content-Type ~ "(text|application)/(html|xml|javascript|json|css)" ||
        beresp.http.Content-Type ~ "application/x-javascript") {
        set beresp.do_gzip = true;
    }
    
    return (deliver);
}

sub vcl_deliver {
    # Add cache status headers
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
        set resp.http.X-Cache-Hits = obj.hits;
    } else {
        set resp.http.X-Cache = "MISS";
    }
    
    # Add backend information
    set resp.http.X-Served-By = server.hostname;
    
    # Remove internal headers
    unset resp.http.X-Varnish;
    unset resp.http.Via;
    unset resp.http.Age;
    
    return (deliver);
}

sub vcl_hit {
    # Handle PURGE requests
    if (req.method == "PURGE") {
        return (synth(200, "Purged"));
    }
    
    return (deliver);
}

sub vcl_miss {
    # Handle PURGE requests
    if (req.method == "PURGE") {
        return (synth(404, "Not in cache"));
    }
    
    return (fetch);
}

sub vcl_synth {
    if (resp.status == 720) {
        # Redirect to HTTPS
        set resp.status = 301;
        set resp.http.Location = "https://" + req.http.host + req.url;
        return (deliver);
    }
    
    return (deliver);
}
