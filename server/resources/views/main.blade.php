<html>
    <head>
        <title>{{ $settings->get('branding.site_name') }}</title>

        <base href="{{ $htmlBaseUri }}">

        <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
        <link href='https://fonts.googleapis.com/css?family=Roboto:100,300,400,500,700,400italic' rel='stylesheet' type='text/css'>
        <link rel="icon" type="image/x-icon" href="favicon.ico">


        {{--angular styles begin--}}
		<link href="styles.ec5639b3df4224e48472.bundle.css" rel="stylesheet" />
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
		<script type="text/javascript" src="inline.927b2a20d2c22c193aa5.bundle.js"></script>
        <script type="text/javascript" src="polyfills.8736bd7b7e2705fb4847.bundle.js"></script>
        <script type="text/javascript" src="scripts.7952773edb56bea04d7e.bundle.js"></script>
        <script type="text/javascript" src="vendor.2afcb15b0a74ae78e6de.bundle.js"></script>
        <script type="text/javascript" src="main.f3692852c1e800846b00.bundle.js"></script>
		{{--angular scripts end--}}

        @if ($settings->has('custom_code.js'))
            <script>{!! $settings->get('custom_code.js') !!}</script>
        @endif
	</body>
</html>