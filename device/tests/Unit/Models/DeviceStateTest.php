<?php

declare(strict_types=1);

use App\Models\DeviceState;

it('retrieves value by key', function () {
    DeviceState::setValue('test_key', 'test_value');

    $result = DeviceState::getValue('test_key');

    expect($result)->toBe('test_value');
});

it('returns default when key does not exist', function () {
    $result = DeviceState::getValue('non_existent_key', 'default_value');

    expect($result)->toBe('default_value');
});

it('returns null default when key does not exist and no default provided', function () {
    $result = DeviceState::getValue('non_existent_key');

    expect($result)->toBeNull();
});

it('returns null when value is explicitly set to null', function () {
    DeviceState::setValue('null_key', null);

    $result = DeviceState::getValue('null_key');

    expect($result)->toBeNull();
});

it('updates existing key value', function () {
    DeviceState::setValue('update_key', 'original_value');
    DeviceState::setValue('update_key', 'updated_value');

    $result = DeviceState::getValue('update_key');

    expect($result)->toBe('updated_value');
});

it('creates new key value pair', function () {
    DeviceState::setValue('new_key', 'new_value');

    $count = DeviceState::where('key', 'new_key')->count();

    expect($count)->toBe(1);
});

it('uses updateOrCreate to preserve existing record on update', function () {
    DeviceState::setValue('preserve_key', 'first_value');
    $originalId = DeviceState::where('key', 'preserve_key')->first()->id;

    DeviceState::setValue('preserve_key', 'second_value');
    $newId = DeviceState::where('key', 'preserve_key')->first()->id;

    expect($newId)->toBe($originalId);
});

it('handles empty string value', function () {
    DeviceState::setValue('empty_key', '');

    $result = DeviceState::getValue('empty_key');

    expect($result)->toBe('');
});

it('handles special characters in value', function () {
    DeviceState::setValue('special_key', 'value with "quotes" and <special> chars');

    $result = DeviceState::getValue('special_key');

    expect($result)->toBe('value with "quotes" and <special> chars');
});

it('handles numeric string values', function () {
    DeviceState::setValue('numeric_key', '12345');

    $result = DeviceState::getValue('numeric_key');

    expect($result)->toBe('12345');
});

it('can set and retrieve multiple independent keys', function () {
    DeviceState::setValue('key1', 'value1');
    DeviceState::setValue('key2', 'value2');
    DeviceState::setValue('key3', 'value3');

    expect(DeviceState::getValue('key1'))->toBe('value1')
        ->and(DeviceState::getValue('key2'))->toBe('value2')
        ->and(DeviceState::getValue('key3'))->toBe('value3');
});

it('getValue returns first match when duplicate keys exist (should not happen due to unique constraint)', function () {
    DeviceState::setValue('duplicate_key', 'first');
    DeviceState::setValue('duplicate_key', 'second');

    $result = DeviceState::getValue('duplicate_key');

    expect($result)->toBe('second');
});

it('handles key with spaces', function () {
    DeviceState::setValue('key with spaces', 'value with spaces');

    $result = DeviceState::getValue('key with spaces');

    expect($result)->toBe('value with spaces');
});

it('handles unicode characters in key and value', function () {
    DeviceState::setValue('ключ', 'значение');
    DeviceState::setValue('キー', '値');

    expect(DeviceState::getValue('ключ'))->toBe('значение')
        ->and(DeviceState::getValue('キー'))->toBe('値');
});

it('setValue with null value updates existing record to null', function () {
    DeviceState::setValue('nullable_key', 'some_value');
    DeviceState::setValue('nullable_key', null);

    $result = DeviceState::getValue('nullable_key');

    expect($result)->toBeNull();
});

it('default parameter is used only when key does not exist', function () {
    DeviceState::setValue('existing_key', '');

    expect(DeviceState::getValue('existing_key', 'default'))->toBe('')
        ->and(DeviceState::getValue('missing_key', 'default'))->toBe('default');
});
