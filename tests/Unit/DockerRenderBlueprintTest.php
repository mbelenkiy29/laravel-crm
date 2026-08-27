<?php

$root = dirname(__DIR__, 2);

test('docker and render files exist at the repository root', function () use ($root) {
    expect(file_exists($root.'/Dockerfile'))->toBeTrue();
    expect(file_exists($root.'/docker-compose.yml'))->toBeTrue();
    expect(file_exists($root.'/render.yaml'))->toBeTrue();
});

test('dockerfile uses php 8.3 fpm and nginx rather than the hub 2.0.1 image', function () use ($root) {
    $dockerfile = file_get_contents($root.'/Dockerfile');

    expect($dockerfile)->toContain('php:8.3-fpm');
    expect($dockerfile)->toContain('nginx');
    expect($dockerfile)->toContain('composer:2');
    expect($dockerfile)->toContain('calendar');
    expect($dockerfile)->toContain('gd');
    expect($dockerfile)->toContain('intl');
    expect($dockerfile)->toContain('America/New_York');
    expect($dockerfile)->not->toContain('webkul/krayin:2.0.1');
});

test('php.ini sets the required memory and timeout limits', function () use ($root) {
    $ini = file_get_contents($root.'/docker/php/krayin.ini');

    expect($ini)->toContain('memory_limit = 4G');
    expect($ini)->toContain('max_execution_time = 360');
    expect($ini)->toContain('date.timezone = America/New_York');
});

test('compose bind-mounts only ./storage and does not expose phpmyadmin or passwords', function () use ($root) {
    $compose = file_get_contents($root.'/docker-compose.yml');

    expect($compose)->toContain('./storage:/var/www/html/storage');
    expect($compose)->toContain('mysql:8.0.40');
    expect($compose)->not->toContain('phpmyadmin');
    expect($compose)->not->toContain('webkul/krayin:2.0.1');
    expect($compose)->not->toContain('DB_PASSWORD: krayin');
    expect($compose)->not->toContain('MYSQL_ROOT_PASSWORD: root');

    preg_match('/  krayin:\n(?:.*\n)*?    volumes:\n((?:      - .*\n)+)/', $compose, $matches);
    expect($matches[1] ?? '')->toBe("      - ./storage:/var/www/html/storage\n");
});

test('render first-boot is non-interactive and does not parse generateValue passwords via the installer', function () use ($root) {
    $render = file_get_contents($root.'/render.yaml');
    $entrypoint = file_get_contents($root.'/docker/entrypoint.sh');
    $mysqlDockerfile = file_get_contents($root.'/docker/mysql/Dockerfile');
    $charset = file_get_contents($root.'/docker/mysql/charset.cnf');

    expect($render)->toContain('runtime: docker');
    expect($render)->toContain('dockerfilePath: ./Dockerfile');
    expect($render)->not->toContain('webkul/krayin:2.0.1');
    expect($render)->toContain('type: pserv');
    expect($render)->toContain('mountPath: /var/www/html/storage');
    expect($render)->toContain('APP_KEY');
    expect($render)->toContain('generateValue: true');
    expect($render)->not->toMatch('/key: APP_KEY\n\s+value:/');
    expect($render)->toContain('MAIL_MAILER');
    expect($render)->toContain('value: log');
    expect($render)->toContain('APP_DEBUG');
    expect($render)->toContain('value: "false"');
    expect($mysqlDockerfile)->toContain('mysql:8.0.40');
    expect($charset)->toContain('utf8mb4_unicode_ci');
    expect($entrypoint)->toContain('migrate:fresh --force');
    expect($entrypoint)->toContain('db:seed --force');
    expect($entrypoint)->toContain('--skip-admin-creation');
    expect($entrypoint)->toContain('--no-interaction');
    expect($entrypoint)->toContain('json_encode');
});
