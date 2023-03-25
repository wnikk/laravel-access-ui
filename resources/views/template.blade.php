<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Rules and Owners example</title>

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Ubuntu:regular,bold">
    <style>
        body {font-family: Ubuntu, roman, serif;}
    </style>

    @include('accessUi::links')

    @include('accessUi::main')

</head>
<body>
</body>
</html>

