@extends('layouts.app')

@section('header')
    <h2 class="text-xl font-semibold leading-tight text-gray-800">
        Dashboard
    </h2>
@endsection

@section('content')
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">
                    <p>Welcome, <strong>{{ Auth::user()->name }}</strong>! You're logged in.</p>
                    <p class="mt-2 text-sm text-gray-500">Your subdomain: <code class="bg-gray-100 px-2 py-1 rounded text-indigo-600">{{ Auth::user()->username }}.vibecodepc.com</code></p>
                </div>
            </div>
        </div>
    </div>
@endsection
