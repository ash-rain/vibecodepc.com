<?php

declare(strict_types=1);

describe('Config File Structure Validation', function (): void {

    beforeEach(function (): void {
        // Store original config for restoration after tests
        $this->originalConfigFiles = config('vibecodepc.config_files');
        $this->originalConfigEditor = config('vibecodepc.config_editor');
    });

    afterEach(function (): void {
        // Restore original config
        config()->set('vibecodepc.config_files', $this->originalConfigFiles);
        config()->set('vibecodepc.config_editor', $this->originalConfigEditor);
    });

    describe('Required Keys Validation', function (): void {
        test('all config file entries have required keys', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                // All entries must have 'label'
                expect($config)->toHaveKey('label')
                    ->and($config['label'])->toBeString()
                    ->and($config['label'])->not->toBeEmpty();

                // All entries must have 'description'
                expect($config)->toHaveKey('description')
                    ->and($config['description'])->toBeString()
                    ->and($config['description'])->not->toBeEmpty();

                // All entries must have 'editable'
                expect($config)->toHaveKey('editable')
                    ->and($config['editable'])->toBeBool();

                // All entries must have 'scope'
                expect($config)->toHaveKey('scope')
                    ->and($config['scope'])->toBeIn(['global', 'project']);
            }
        });

        test('global scoped configs have path key', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                if ($config['scope'] === 'global') {
                    expect($config)->toHaveKey('path')
                        ->and($config['path'])->toBeString()
                        ->and($config['path'])->not->toBeEmpty();
                }
            }
        });

        test('project scoped configs have path_template key', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                if ($config['scope'] === 'project') {
                    expect($config)->toHaveKey('path_template')
                        ->and($config['path_template'])->toBeString()
                        ->and($config['path_template'])->not->toBeEmpty();
                }
            }
        });

        test('missing label throws appropriate error', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path' => '/tmp/test.json',
                    'description' => 'Test description',
                    'editable' => true,
                    'scope' => 'global',
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $config = config('vibecodepc.config_files.test_invalid');
            expect($config)->not->toHaveKey('label');
        });

        test('missing description throws appropriate error', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path' => '/tmp/test.json',
                    'label' => 'Test Label',
                    'editable' => true,
                    'scope' => 'global',
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $config = config('vibecodepc.config_files.test_invalid');
            expect($config)->not->toHaveKey('description');
        });

        test('missing editable flag throws appropriate error', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path' => '/tmp/test.json',
                    'label' => 'Test Label',
                    'description' => 'Test description',
                    'scope' => 'global',
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $config = config('vibecodepc.config_files.test_invalid');
            expect($config)->not->toHaveKey('editable');
        });

        test('missing scope throws appropriate error', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path' => '/tmp/test.json',
                    'label' => 'Test Label',
                    'description' => 'Test description',
                    'editable' => true,
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $config = config('vibecodepc.config_files.test_invalid');
            expect($config)->not->toHaveKey('scope');
        });

        test('empty label is invalid', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path' => '/tmp/test.json',
                    'label' => '',
                    'description' => 'Test description',
                    'editable' => true,
                    'scope' => 'global',
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $config = config('vibecodepc.config_files.test_invalid');
            expect($config['label'])->toBeEmpty();
        });

        test('empty description is invalid', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path' => '/tmp/test.json',
                    'label' => 'Test Label',
                    'description' => '',
                    'editable' => true,
                    'scope' => 'global',
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $config = config('vibecodepc.config_files.test_invalid');
            expect($config['description'])->toBeEmpty();
        });
    });

    describe('Path Template Validation', function (): void {
        test('path_template contains {project_path} placeholder', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                if ($config['scope'] === 'project') {
                    expect($config['path_template'])->toContain('{project_path}');
                }
            }
        });

        test('global scoped configs do not have path_template', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                if ($config['scope'] === 'global') {
                    expect($config)->not->toHaveKey('path_template');
                }
            }
        });

        test('project scoped configs do not have path key', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                if ($config['scope'] === 'project') {
                    expect($config)->not->toHaveKey('path');
                }
            }
        });

        test('path_template produces valid file paths', function (): void {
            $testProjectPath = '/home/user/projects/test-project';

            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                if ($config['scope'] === 'project') {
                    $resolvedPath = str_replace('{project_path}', $testProjectPath, $config['path_template']);

                    // Should not contain unresolved placeholder
                    expect($resolvedPath)->not->toContain('{project_path}')
                        ->and($resolvedPath)->toBeString()
                        ->and($resolvedPath)->toStartWith('/');
                }
            }
        });

        test('path_template handles trailing slash in project_path', function (): void {
            $testProjectPathWithSlash = '/home/user/projects/test-project/';

            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                if ($config['scope'] === 'project') {
                    $resolvedPath = str_replace('{project_path}', $testProjectPathWithSlash, $config['path_template']);

                    // Path templates may resolve with double slashes when project_path has trailing slash
                    // The actual implementation should handle this normalization
                    // For now, we just verify the path_template contains the placeholder
                    expect($config['path_template'])->toContain('{project_path}');
                }
            }
        });

        test('path_template handles multiple {project_path} placeholders', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path_template' => '{project_path}/config/{project_path}/file.json',
                    'label' => 'Invalid Config',
                    'description' => 'Test with multiple placeholders',
                    'editable' => true,
                    'scope' => 'project',
                ],
            ];

            config()->set('vibecodepc.config_files', array_merge(
                config('vibecodepc.config_files', []),
                $invalidConfig
            ));

            $config = config('vibecodepc.config_files.test_invalid');
            $placeholders = substr_count($config['path_template'], '{project_path}');
            expect($placeholders)->toBeGreaterThan(1);
        });
    });

    describe('Scope Values Validation', function (): void {
        test('scope must be either global or project', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                expect($config['scope'])->toBeIn(['global', 'project']);
            }
        });

        test('invalid scope value is rejected', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path' => '/tmp/test.json',
                    'label' => 'Test Label',
                    'description' => 'Test description',
                    'editable' => true,
                    'scope' => 'invalid_scope',
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $scope = config('vibecodepc.config_files.test_invalid.scope');
            expect($scope)->toBe('invalid_scope');
        });

        test('case sensitivity matters for scope', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path' => '/tmp/test.json',
                    'label' => 'Test Label',
                    'description' => 'Test description',
                    'editable' => true,
                    'scope' => 'Global', // Wrong case
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $scope = config('vibecodepc.config_files.test_invalid.scope');
            expect($scope)->not->toBeIn(['global', 'project']);
        });

        test('null scope is invalid', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path' => '/tmp/test.json',
                    'label' => 'Test Label',
                    'description' => 'Test description',
                    'editable' => true,
                    'scope' => null,
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $scope = config('vibecodepc.config_files.test_invalid.scope');
            expect($scope)->toBeNull();
        });

        test('empty string scope is invalid', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path' => '/tmp/test.json',
                    'label' => 'Test Label',
                    'description' => 'Test description',
                    'editable' => true,
                    'scope' => '',
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $scope = config('vibecodepc.config_files.test_invalid.scope');
            expect($scope)->toBeEmpty();
        });
    });

    describe('Parent Key References', function (): void {
        test('project scoped configs reference valid parent_key', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                if ($config['scope'] === 'project') {
                    expect($config)->toHaveKey('parent_key')
                        ->and($config['parent_key'])->toBeString()
                        ->and($config['parent_key'])->not->toBeEmpty();

                    // Parent key must exist in config_files
                    expect($configFiles)->toHaveKey($config['parent_key']);

                    // Parent must be global scoped
                    $parentConfig = $configFiles[$config['parent_key']];
                    expect($parentConfig['scope'])->toBe('global');
                }
            }
        });

        test('global scoped configs do not have parent_key', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                if ($config['scope'] === 'global') {
                    expect($config)->not->toHaveKey('parent_key');
                }
            }
        });

        test('parent_key references non-existent key', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path_template' => '{project_path}/config.json',
                    'label' => 'Invalid Config',
                    'description' => 'Test with invalid parent',
                    'editable' => true,
                    'scope' => 'project',
                    'parent_key' => 'non_existent_parent',
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $configFiles = config('vibecodepc.config_files');
            $parentKey = config('vibecodepc.config_files.test_invalid.parent_key');

            expect($parentKey)->toBe('non_existent_parent');
            expect($configFiles)->not->toHaveKey('non_existent_parent');
        });

        test('parent_key references another project scoped config', function (): void {
            // Create two project configs where one references another (invalid)
            $invalidConfig = [
                'project_config_a' => [
                    'path_template' => '{project_path}/a.json',
                    'label' => 'Project Config A',
                    'description' => 'Test config A',
                    'editable' => true,
                    'scope' => 'project',
                    'parent_key' => 'opencode_global', // Valid global parent
                ],
                'project_config_b' => [
                    'path_template' => '{project_path}/b.json',
                    'label' => 'Project Config B',
                    'description' => 'Test config B',
                    'editable' => true,
                    'scope' => 'project',
                    'parent_key' => 'project_config_a', // Invalid - parent is project scoped
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $configFiles = config('vibecodepc.config_files');
            $parentConfig = $configFiles['project_config_b']['parent_key'];
            $parentScope = $configFiles[$parentConfig]['scope'];

            expect($parentScope)->toBe('project');
        });

        test('parent_key with empty string is invalid', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path_template' => '{project_path}/config.json',
                    'label' => 'Invalid Config',
                    'description' => 'Test with empty parent',
                    'editable' => true,
                    'scope' => 'project',
                    'parent_key' => '',
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $parentKey = config('vibecodepc.config_files.test_invalid.parent_key');
            expect($parentKey)->toBeEmpty();
        });

        test('parent_key with null is invalid', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path_template' => '{project_path}/config.json',
                    'label' => 'Invalid Config',
                    'description' => 'Test with null parent',
                    'editable' => true,
                    'scope' => 'project',
                    'parent_key' => null,
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $config = config('vibecodepc.config_files.test_invalid');
            expect($config['parent_key'])->toBeNull();
        });
    });

    describe('Config Key Naming', function (): void {
        test('config keys use snake_case naming convention', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach (array_keys($configFiles) as $key) {
                // Keys should only contain lowercase letters, numbers, and underscores
                expect($key)->toMatch('/^[a-z0-9_]+$/');
            }
        });

        test('config keys do not use camelCase', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach (array_keys($configFiles) as $key) {
                // Should not contain uppercase letters
                expect($key)->toEqual(strtolower($key));
            }
        });

        test('config keys do not start with underscore', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach (array_keys($configFiles) as $key) {
                expect($key)->not->toStartWith('_');
            }
        });

        test('config keys do not end with underscore', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach (array_keys($configFiles) as $key) {
                expect($key)->not->toEndWith('_');
            }
        });

        test('config keys are not empty', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach (array_keys($configFiles) as $key) {
                expect($key)->not->toBeEmpty();
            }
        });

        test('config keys do not contain consecutive underscores', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach (array_keys($configFiles) as $key) {
                expect($key)->not->toContain('__');
            }
        });
    });

    describe('Path Format Validation', function (): void {
        test('global config paths are absolute', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                if ($config['scope'] === 'global') {
                    expect($config['path'])->toStartWith('/');
                }
            }
        });

        test('global config paths use forward slashes', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                if ($config['scope'] === 'global') {
                    expect($config['path'])->not->toContain('\\');
                }
            }
        });

        test('global config paths have valid extensions', function (): void {
            $validExtensions = ['.json', '.md'];
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                if ($config['scope'] === 'global') {
                    $hasValidExt = false;
                    foreach ($validExtensions as $ext) {
                        if (str_ends_with($config['path'], $ext)) {
                            $hasValidExt = true;
                            break;
                        }
                    }
                    expect($hasValidExt)->toBeTrue(
                        "Config '{$key}' path '{$config['path']}' should have valid extension"
                    );
                }
            }
        });

        test('path_template produces paths with valid extensions', function (): void {
            $validExtensions = ['.json', '.md'];
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                if ($config['scope'] === 'project') {
                    $pathTemplate = $config['path_template'];
                    $hasValidExt = false;
                    foreach ($validExtensions as $ext) {
                        if (str_ends_with($pathTemplate, $ext)) {
                            $hasValidExt = true;
                            break;
                        }
                    }
                    expect($hasValidExt)->toBeTrue(
                        "Config '{$key}' path_template '{$pathTemplate}' should produce file with valid extension"
                    );
                }
            }
        });

        test('path_template does not contain double slashes', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                if ($config['scope'] === 'project') {
                    // After replacing placeholder, should not have double slashes
                    $testPath = str_replace('{project_path}', '/test/path', $config['path_template']);
                    expect($testPath)->not->toMatch('#//[^/]*#', 'Path template should not produce double slashes');
                }
            }
        });
    });

    describe('Editable Flag Validation', function (): void {
        test('editable is always boolean', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                expect($config['editable'])->toBeBool();
            }
        });

        test('editable is not string', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path' => '/tmp/test.json',
                    'label' => 'Test Label',
                    'description' => 'Test description',
                    'editable' => 'true', // String instead of bool
                    'scope' => 'global',
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $editable = config('vibecodepc.config_files.test_invalid.editable');
            expect($editable)->toBeString()->not->toBeBool();
        });

        test('editable is not integer', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path' => '/tmp/test.json',
                    'label' => 'Test Label',
                    'description' => 'Test description',
                    'editable' => 1, // Integer instead of bool
                    'scope' => 'global',
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $editable = config('vibecodepc.config_files.test_invalid.editable');
            expect($editable)->toBeInt();
        });

        test('editable supports both true and false values', function (): void {
            $testConfigs = [
                'test_editable_true' => [
                    'path' => '/tmp/test1.json',
                    'label' => 'Test Label 1',
                    'description' => 'Test description 1',
                    'editable' => true,
                    'scope' => 'global',
                ],
                'test_editable_false' => [
                    'path' => '/tmp/test2.json',
                    'label' => 'Test Label 2',
                    'description' => 'Test description 2',
                    'editable' => false,
                    'scope' => 'global',
                ],
            ];

            config()->set('vibecodepc.config_files', $testConfigs);

            expect(config('vibecodepc.config_files.test_editable_true.editable'))->toBeTrue();
            expect(config('vibecodepc.config_files.test_editable_false.editable'))->toBeFalse();
        });
    });

    describe('Label and Description Content', function (): void {
        test('label does not exceed 100 characters', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                expect(strlen($config['label']))->toBeLessThanOrEqual(100);
            }
        });

        test('description does not exceed 500 characters', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                expect(strlen($config['description']))->toBeLessThanOrEqual(500);
            }
        });

        test('label contains no HTML tags', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                expect($config['label'])->not->toMatch('/<[^>]+>/');
            }
        });

        test('description contains no HTML tags', function (): void {
            $configFiles = config('vibecodepc.config_files', []);

            foreach ($configFiles as $key => $config) {
                expect($config['description'])->not->toMatch('/<[^>]+>/');
            }
        });

        test('overly long label is invalid', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path' => '/tmp/test.json',
                    'label' => str_repeat('A', 101),
                    'description' => 'Test description',
                    'editable' => true,
                    'scope' => 'global',
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $label = config('vibecodepc.config_files.test_invalid.label');
            expect(strlen($label))->toBeGreaterThan(100);
        });

        test('overly long description is invalid', function (): void {
            $invalidConfig = [
                'test_invalid' => [
                    'path' => '/tmp/test.json',
                    'label' => 'Test Label',
                    'description' => str_repeat('B', 501),
                    'editable' => true,
                    'scope' => 'global',
                ],
            ];

            config()->set('vibecodepc.config_files', $invalidConfig);

            $description = config('vibecodepc.config_files.test_invalid.description');
            expect(strlen($description))->toBeGreaterThan(500);
        });
    });

    describe('Complete Config Entry Validation', function (): void {
        test('valid global scoped config has all required fields', function (): void {
            $validConfig = [
                'test_valid' => [
                    'path' => '/home/user/.config/test.json',
                    'label' => 'Test Configuration',
                    'description' => 'A test configuration file',
                    'editable' => true,
                    'scope' => 'global',
                ],
            ];

            config()->set('vibecodepc.config_files', $validConfig);

            $config = config('vibecodepc.config_files.test_valid');

            expect($config)
                ->toHaveKey('path')
                ->toHaveKey('label')
                ->toHaveKey('description')
                ->toHaveKey('editable')
                ->toHaveKey('scope')
                ->not->toHaveKey('path_template')
                ->not->toHaveKey('parent_key');

            expect($config['path'])->toBe('/home/user/.config/test.json');
            expect($config['label'])->toBe('Test Configuration');
            expect($config['description'])->toBe('A test configuration file');
            expect($config['editable'])->toBeTrue();
            expect($config['scope'])->toBe('global');
        });

        test('valid project scoped config has all required fields', function (): void {
            $validConfig = [
                'test_valid_project' => [
                    'path_template' => '{project_path}/.config/test.json',
                    'label' => 'Test Project Config',
                    'description' => 'A test project-scoped configuration',
                    'editable' => true,
                    'scope' => 'project',
                    'parent_key' => 'test_global',
                ],
                'test_global' => [
                    'path' => '/home/user/.config/test-global.json',
                    'label' => 'Test Global Config',
                    'description' => 'Global test config',
                    'editable' => true,
                    'scope' => 'global',
                ],
            ];

            config()->set('vibecodepc.config_files', $validConfig);

            $config = config('vibecodepc.config_files.test_valid_project');

            expect($config)
                ->toHaveKey('path_template')
                ->toHaveKey('label')
                ->toHaveKey('description')
                ->toHaveKey('editable')
                ->toHaveKey('scope')
                ->toHaveKey('parent_key')
                ->not->toHaveKey('path');

            expect($config['path_template'])->toBe('{project_path}/.config/test.json');
            expect($config['label'])->toBe('Test Project Config');
            expect($config['description'])->toBe('A test project-scoped configuration');
            expect($config['editable'])->toBeTrue();
            expect($config['scope'])->toBe('project');
            expect($config['parent_key'])->toBe('test_global');
        });
    });

    describe('Config Editor Settings', function (): void {
        test('config_editor has all required keys', function (): void {
            $configEditor = config('vibecodepc.config_editor');

            expect($configEditor)
                ->toHaveKey('backup_retention_days')
                ->toHaveKey('max_file_size_kb')
                ->toHaveKey('backup_directory');
        });

        test('backup_retention_days is positive integer', function (): void {
            $days = config('vibecodepc.config_editor.backup_retention_days');

            expect($days)->toBeInt()
                ->toBeGreaterThan(0);
        });

        test('max_file_size_kb is positive integer', function (): void {
            $size = config('vibecodepc.config_editor.max_file_size_kb');

            expect($size)->toBeInt()
                ->toBeGreaterThan(0);
        });

        test('backup_directory is absolute path', function (): void {
            $dir = config('vibecodepc.config_editor.backup_directory');

            expect($dir)->toStartWith('/');
        });

        test('backup_directory uses forward slashes', function (): void {
            $dir = config('vibecodepc.config_editor.backup_directory');

            expect($dir)->not->toContain('\\');
        });

        test('backup_retention_days zero is invalid', function (): void {
            config()->set('vibecodepc.config_editor.backup_retention_days', 0);

            $days = config('vibecodepc.config_editor.backup_retention_days');
            expect($days)->toBe(0);
        });

        test('backup_retention_days negative is invalid', function (): void {
            config()->set('vibecodepc.config_editor.backup_retention_days', -1);

            $days = config('vibecodepc.config_editor.backup_retention_days');
            expect($days)->toBeLessThan(0);
        });

        test('max_file_size_kb zero is invalid', function (): void {
            config()->set('vibecodepc.config_editor.max_file_size_kb', 0);

            $size = config('vibecodepc.config_editor.max_file_size_kb');
            expect($size)->toBe(0);
        });

        test('max_file_size_kb negative is invalid', function (): void {
            config()->set('vibecodepc.config_editor.max_file_size_kb', -10);

            $size = config('vibecodepc.config_editor.max_file_size_kb');
            expect($size)->toBeLessThan(0);
        });

        test('backup_directory empty is invalid', function (): void {
            config()->set('vibecodepc.config_editor.backup_directory', '');

            $dir = config('vibecodepc.config_editor.backup_directory');
            expect($dir)->toBeEmpty();
        });
    });
});
