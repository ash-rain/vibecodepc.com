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

// Type Casting Tests
it('casts integer to string when storing', function () {
    DeviceState::setValue('int_key', (string) 42);

    $result = DeviceState::getValue('int_key');

    expect($result)->toBe('42')
        ->and($result)->toBeString();
});

it('casts boolean true to string when storing', function () {
    DeviceState::setValue('bool_true_key', (string) true);

    $result = DeviceState::getValue('bool_true_key');

    expect($result)->toBe('1')
        ->and($result)->toBeString();
});

it('casts boolean false to string when storing', function () {
    DeviceState::setValue('bool_false_key', (string) false);

    $result = DeviceState::getValue('bool_false_key');

    expect($result)->toBe('')
        ->and($result)->toBeString();
});

it('casts float to string when storing', function () {
    DeviceState::setValue('float_key', (string) 3.14159);

    $result = DeviceState::getValue('float_key');

    expect($result)->toBe('3.14159')
        ->and($result)->toBeString();
});

it('stores and retrieves JSON encoded array', function () {
    $array = ['name' => 'test', 'value' => 123, 'active' => true];
    $json = json_encode($array);

    DeviceState::setValue('json_key', $json);

    $result = DeviceState::getValue('json_key');
    $decoded = json_decode($result, true);

    expect($result)->toBeString()
        ->and($decoded)->toBe($array);
});

it('stores and retrieves JSON encoded object', function () {
    $object = (object) ['id' => 1, 'name' => 'Device'];
    $json = json_encode($object);

    DeviceState::setValue('object_key', $json);

    $result = DeviceState::getValue('object_key');
    $decoded = json_decode($result);

    expect($result)->toBeString()
        ->and($decoded->id)->toBe(1)
        ->and($decoded->name)->toBe('Device');
});

it('handles JSON with unicode characters', function () {
    $data = ['message' => 'Hello 世界 🌍'];
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);

    DeviceState::setValue('unicode_json_key', $json);

    $result = DeviceState::getValue('unicode_json_key');
    $decoded = json_decode($result, true);

    expect($decoded['message'])->toBe('Hello 世界 🌍');
});

it('casts zero integer to string', function () {
    DeviceState::setValue('zero_key', (string) 0);

    $result = DeviceState::getValue('zero_key');

    expect($result)->toBe('0')
        ->and($result)->not->toBeNull()
        ->and($result)->toBeString();
});

it('casts negative integer to string', function () {
    DeviceState::setValue('negative_key', (string) -42);

    $result = DeviceState::getValue('negative_key');

    expect($result)->toBe('-42')
        ->and($result)->toBeString();
});

it('handles scientific notation float', function () {
    DeviceState::setValue('scientific_key', (string) 1.23e10);

    $result = DeviceState::getValue('scientific_key');

    expect($result)->toBe('12300000000')
        ->and($result)->toBeString();
});

it('retrieves value with correct type casting from database', function () {
    DeviceState::create(['key' => 'cast_key', 'value' => '123']);

    $result = DeviceState::getValue('cast_key');

    expect($result)->toBe('123')
        ->and($result)->toBeString()
        ->and(is_numeric($result))->toBeTrue();
});

it('stores and retrieves string representation of null', function () {
    DeviceState::setValue('null_string_key', 'null');

    $result = DeviceState::getValue('null_string_key');

    expect($result)->toBe('null')
        ->and($result)->not->toBeNull()
        ->and($result)->toBeString();
});

it('handles empty JSON object', function () {
    DeviceState::setValue('empty_json_key', '{}');

    $result = DeviceState::getValue('empty_json_key');
    $decoded = json_decode($result, true);

    expect($result)->toBe('{}')
        ->and($decoded)->toBe([]);
});

it('handles empty JSON array', function () {
    DeviceState::setValue('empty_array_key', '[]');

    $result = DeviceState::getValue('empty_array_key');
    $decoded = json_decode($result, true);

    expect($result)->toBe('[]')
        ->and($decoded)->toBe([]);
});

it('preserves string boolean representations', function () {
    DeviceState::setValue('string_true', 'true');
    DeviceState::setValue('string_false', 'false');

    expect(DeviceState::getValue('string_true'))->toBe('true')
        ->and(DeviceState::getValue('string_false'))->toBe('false');
});

it('handles large numeric strings without casting', function () {
    $largeNumber = '999999999999999999999999999999';
    DeviceState::setValue('large_number_key', $largeNumber);

    $result = DeviceState::getValue('large_number_key');

    expect($result)->toBe($largeNumber)
        ->and($result)->toBeString();
});
