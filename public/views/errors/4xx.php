<?php
/**
 * Error page template for 4xx client errors
 * Variables available: $statusCode, $message
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $statusCode ?? 400; ?> Error - ReuseIT</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        html, body {
            width: 100%;
            height: 100%;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .error-container {
            background: white;
            border-radius: 8px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 60px 40px;
            text-align: center;
            max-width: 500px;
            margin: 20px;
        }
        
        .error-code {
            font-size: 72px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .error-title {
            font-size: 28px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 15px;
        }
        
        .error-message {
            font-size: 16px;
            color: #718096;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .error-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-secondary {
            background: #f0f0f0;
            color: #2d3748;
        }
        
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        
        .error-details {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #a0aec0;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-code"><?php echo $statusCode ?? 400; ?></div>
        <div class="error-title">
            <?php
            $titles = [
                400 => 'Bad Request',
                401 => 'Unauthorized',
                403 => 'Forbidden',
                404 => 'Not Found',
                409 => 'Conflict',
                422 => 'Unprocessable Entity',
            ];
            echo $titles[$statusCode] ?? 'Client Error';
            ?>
        </div>
        <div class="error-message">
            <?php echo htmlspecialchars($message ?? 'An error occurred with your request. Please try again.'); ?>
        </div>
        <div class="error-actions">
            <a href="/" class="btn btn-primary">Go Home</a>
            <button class="btn btn-secondary" onclick="window.history.back()">Go Back</button>
        </div>
        <div class="error-details">
            If the problem persists, please contact support.
        </div>
    </div>
</body>
</html>
