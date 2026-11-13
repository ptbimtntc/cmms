@extends('layouts.guest')

@section('lang', 'en')
@section('title', 'FreeDOMS | Digital Preventive Maintenance')

@section('content')

<div class="landing">

    <x-landing.navbar />

    <main>

        <x-landing.hero />

        <x-landing.pm-overview />

        <x-landing.public-access />

        <x-landing.how-it-works />

        <x-landing.final-cta />

    </main>

    <x-landing.footer />

</div>

@endsection
