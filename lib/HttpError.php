<?php


if(!class_exists('HttpError')) {

   class HttpError {
    
    private static $errorMessages = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        408 => 'Request Timeout',
        429 => 'Too Many Requests',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Timeout'
    ];
    
    /**
     * Send HTTP error response
     * @param int $code HTTP status code
     * @param string $message Custom message (optional)
     * @param bool $display Show error page (default true)
     */
    public static function send($code = 404, $message = null, $display = true) {
        // Set the HTTP response code
        http_response_code($code);
        
        // Get default message if none provided
        $statusText = self::$errorMessages[$code] ?? 'Error';
        $message = $message ?? $statusText;
        
        if ($display) {
            self::display($code, $statusText, $message);
        }
        
        exit;
    }
    
    /**
     * Display error page
     */
    private static function display($code, $statusText, $message) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo $code; ?> - <?php echo $statusText; ?></title>
            <style>
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    background: #f5f5f5;
                    margin: 0;
                    padding: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    min-height: 100vh;
                }
                .error-container {
                    background: white;
                    padding: 40px;
                    border-radius: 8px;
                    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                    text-align: center;
                    max-width: 500px;
                }
                .error-code {
                    font-size: 72px;
                    font-weight: bold;
                    color: #e74c3c;
                    margin: 0;
                }
                .error-title {
                    font-size: 24px;
                    color: #333;
                    margin: 10px 0;
                }
                .error-message {
                    font-size: 16px;
                    color: #666;
                    margin: 20px 0;
                }
                .back-link {
                    display: inline-block;
                    margin-top: 20px;
                    padding: 10px 20px;
                    background: #3498db;
                    color: white;
                    text-decoration: none;
                    border-radius: 4px;
                    transition: background 0.3s;
                }
                .back-link:hover {
                    background: #2980b9;
                }
            </style>
        </head>
      <body class="p-4">
            <div class="error-container">
                <h1 class="error-code"><?php echo $code; ?></h1>
                <h2 class="error-title"><?php echo $statusText; ?></h2>
                <p class="error-message"><?php echo htmlspecialchars($message); ?></p>


<form method="get" action="/" class="needs-validation" novalidate>

    <input type="hidden" name="pg" value="cb_dashboard">

    <div class="mb-3">
        <label for="pass" class="form-label">Pass</label>
        <input
            type="text"
            class="form-control"
            id="pass"
            name="pass"
            required
            maxlength="255"
        >
        <div class="invalid-feedback">
            Required.
        </div>
    </div>

    <button type="submit" class="btn btn-primary back-link">Submit</button>
</form>
       <a href="/enter_access_code.php" class="back-link">Go to Homepage</a>
            </div>

<script>
(() => {
    document.querySelectorAll('.needs-validation').forEach(form => {
        form.addEventListener('submit', e => {
            if (!form.checkValidity()) {
                e.preventDefault();
                e.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });
})();
</script>





         
        </body>
        </html>
        <?php
    }
    
    // Convenience methods for common errors
    public static function notFound($message = null) {
        self::send(404, $message);
    }
    
    public static function forbidden($message = null) {
        self::send(403, $message);
    }
    
    public static function unauthorized($message = null) {
        self::send(401, $message);
    }
    
    public static function badRequest($message = null) {
        self::send(400, $message);
    }
    
    public static function serverError($message = null) {
        self::send(500, $message);
    }
    
    public static function serviceUnavailable($message = null) {
        self::send(503, $message);
    }
}

}

