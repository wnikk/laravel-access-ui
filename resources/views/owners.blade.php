<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Owners example</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Ubuntu:regular,bold">
    <style>
        body {font-family: Ubuntu, roman, serif;}
    </style>

    @include('accessUi::links')

    <script>
        document.addEventListener("DOMContentLoaded", function(e) {
            accessUi.init(document.body, 'owners',{
                csrfToken: document.querySelector('meta[name="csrf-token"]')?.content,
                routeOwners: {
                    list:   '{{ $routes['list'] }}',
                    create: '{{ $routes['create'] }}',
                    update: '{{ $routes['update'] }}',
                    delete: '{{ $routes['delete'] }}',
                },
            });
        });
    </script>

</head>
<body>
</body>
</html>

