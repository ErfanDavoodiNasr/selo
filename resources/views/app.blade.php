<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SELO (سلو)</title>
    <link rel="icon" href="{{ $basePath }}/favicon.ico" sizes="any">
    <link rel="stylesheet" href="{{ $basePath }}/assets/css/fonts.css">
    <script>
        window.SELO_CONFIG = {
            baseUrl: @json($appUrl),
            basePath: @json($basePath),
            app: {
                enable_service_worker: @json($enableServiceWorker),
            },
            realtime: {
                mode: @json($realtimeMode),
            },
        };
    </script>
    <script type="module" crossorigin src="{{ $basePath }}/assets/build/messenger.js"></script>
    <link rel="stylesheet" crossorigin href="{{ $basePath }}/assets/build/messenger.css">
</head>
<body data-theme="light">
    <div id="react-root"></div>
</body>
</html>
