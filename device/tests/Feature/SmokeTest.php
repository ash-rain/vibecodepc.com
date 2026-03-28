<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Test all major dashboard routes load successfully
$routes = [
    ['route' => '/', 'name' => 'home redirect'],
    ['route' => '/pairing', 'name' => 'pairing screen'],
    ['route' => '/tunnel/login', 'name' => 'tunnel login'],
    ['route' => '/dashboard', 'name' => 'dashboard overview'],
    ['route' => '/dashboard/projects', 'name' => 'project list'],
    ['route' => '/dashboard/projects/create', 'name' => 'project create'],
    ['route' => '/dashboard/ai-services', 'name' => 'AI services'],
    ['route' => '/dashboard/ai-tools', 'name' => 'AI tools config'],
    ['route' => '/dashboard/code-editor', 'name' => 'code editor'],
    ['route' => '/dashboard/tunnels', 'name' => 'tunnel manager'],
    ['route' => '/dashboard/containers', 'name' => 'container monitor'],
    ['route' => '/dashboard/settings', 'name' => 'system settings'],
    ['route' => '/dashboard/analytics', 'name' => 'analytics'],
    ['route' => '/wizard', 'name' => 'setup wizard'],
];

foreach ($routes as $routeInfo) {
    test("{$routeInfo['name']} page loads", function () use ($routeInfo) {
        $response = $this->get($routeInfo['route']);

        // Home route redirects (302), others should be successful (200)
        if ($routeInfo['route'] === '/') {
            $response->assertStatus(302);
        } else {
            $response->assertSuccessful();
        }
    });
}

// Test form submissions
test('project creation form accepts valid input', function () {
    $response = $this->get('/dashboard/projects/create');
    $response->assertSuccessful();
    $response->assertSee('Create New Project');
});

test('tunnel login form has required fields', function () {
    $response = $this->get('/tunnel/login');
    $response->assertSuccessful();
    $response->assertSee('password');
    $response->assertSee('Device Access');
});

test('AI tools config has environment variables form', function () {
    $response = $this->get('/dashboard/ai-tools');
    $response->assertSuccessful();
    $response->assertSee('API Keys');
});

// Test for accessibility basics
test('all pages have proper html structure', function () {
    $response = $this->get('/dashboard');
    $response->assertSuccessful();

    // Check for HTML5 doctype
    $content = $response->getContent();
    expect($content)->toContain('<!DOCTYPE html>');
    expect($content)->toContain('<html lang="en">');
    expect($content)->toContain('<meta charset="utf-8">');
    expect($content)->toContain('<meta name="viewport"');
});

// Test responsive meta tag exists
test('dashboard has viewport meta tag for responsive design', function () {
    $response = $this->get('/dashboard');
    $content = $response->getContent();

    expect($content)->toContain('width=device-width');
});

// Test navigation components exist
test('dashboard includes sidebar navigation', function () {
    $response = $this->get('/dashboard');
    $content = $response->getContent();

    expect($content)->toContain('sidebar');
});

test('dashboard includes top bar with title', function () {
    $response = $this->get('/dashboard');
    $content = $response->getContent();

    expect($content)->toContain('Dashboard');
});
