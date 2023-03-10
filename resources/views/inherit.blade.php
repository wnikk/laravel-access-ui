<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Inherit example</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" type="text/css" href="http://fonts.googleapis.com/css?family=Ubuntu:regular,bold">
    <style>
        body {font-family: Ubuntu, roman, serif;}
    </style>

    @include('accessUi::links')

    <script>
        document.addEventListener("DOMContentLoaded", function(e) {
            accessUi.init(document.body, 'inherit',{
                csrfToken: document.querySelector('meta[name="csrf-token"]')?.content,
                routeInherit: {
                    list:   '{{ $routes['list'] }}',
                    create: '{{ $routes['create'] }}',
                    delete: '{{ $routes['delete'] }}',
                },
            });
        });
    </script>

</head>
<body>
</body>
</html>

