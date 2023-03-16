<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Management example</title>

    <link rel="stylesheet" type="text/css" href="//fonts.googleapis.com/css?family=Ubuntu:regular,bold">
    <style>
        body {font-family: Ubuntu, roman, serif;}
        #management-form {
            width: 300px;
            margin: calc(50vh - 200px) auto;
        }
        #management-form select, #management-form button {
            padding: 0.5rem;
            width: 100%;
            margin-bottom: 1rem;
        }
        #management-form button {
            padding: 0.2rem;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function(e) {

            const form = document.getElementById("management-form");
            form.addEventListener("submit", function(e) {
                e.preventDefault();
                let url   = e.submitter.getAttribute('formaction');
                let owner = document.getElementById("owner").value;
                url = url.replace(':owner:', owner);

                window.location.assign(url);

                return false;
            }, false);
        });
    </script>

</head>
<body>

    <form id="management-form" method="get">
        <select id="owner" name="owner" required>
            <option value="">Select ...</option>
            @foreach ($owners as $group)
                <optgroup label="{{ $group['name'] }}">
                    @foreach ($group['list'] as $item)
                        <option value="{{ $item['id'] }}">
                            {{ $item['name'] }}
                            @if ($item['original_id'])
                                (#ID: {{ $item['original_id'] }})
                            @endif
                        </option>
                    @endforeach
                </optgroup>
            @endforeach
        </select>

        @if ($routes['inherit'])
            <button type="submit" formaction="{{ $routes['inherit'] }}">Go to editing inherit...</button>
        @endif

        @if ($routes['permission'])
            <button type="submit" formaction="{{ $routes['permission'] }}">Go to editing permission...</button>
        @endif

    </form>


</body>
</html>

