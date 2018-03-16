<html>
    <head>
        <title>{{ $settings->get('branding.site_name') }}</title>

        <base href="{{ $htmlBaseUri }}">

        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <link href='https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,400italic' rel='stylesheet' type='text/css'>
        <link rel="icon" type="image/x-icon" href="favicon.ico">


        {{--angular styles begin--}}
		<link href="styles.5a87807921b4f3e65da0.bundle.css" rel="stylesheet">
		{{--angular styles end--}}

        @if ($settings->has('custom_code.css'))
            <style>{!! $settings->get('custom_code.css') !!}</style>
        @endif
	</head>

    <body>
        <app-root></app-root>

        <script>
            window.bootstrapData = "{!! $bootstrapData !!}";
        </script>

        {{--angular scripts begin--}}
		<script type="text/javascript" src="inline.e3cd4a9f353994510777.bundle.js"></script>
		<script type="text/javascript" src="polyfills.a26fefeb698715c276bb.bundle.js"></script>
		<script type="text/javascript" src="scripts.0b365efe02c77a3b73b5.bundle.js"></script>
		<script type="text/javascript" src="vendor.9b5bf219d693f3d360e0.bundle.js"></script>
		<script type="text/javascript" src="main.54f9755b9f0a9b8d6a08.bundle.js"></script>
		{{--angular scripts end--}}

        @if ($settings->has('custom_code.js'))
            <script>{!! $settings->get('custom_code.js') !!}</script>
        @endif
	</body>
</html>