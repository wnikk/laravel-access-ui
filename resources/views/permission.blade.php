<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Permission example</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Ubuntu:regular,bold">
    <style>
        body {font-family: Ubuntu, roman, serif;}
    </style>

    @include('accessUi::links')

    <script>
        document.addEventListener("DOMContentLoaded", function(e) {
            accessUi.init(document.body, 'permission',{
                csrfToken: document.querySelector('meta[name="csrf-token"]')?.content,
                routePermission: {
                    list:   '{{ $routes['list'] }}',
                    update: '{{ $routes['update'] }}'
                },
            });
        });
    </script>

</head>
<body>
</body>
</html>

