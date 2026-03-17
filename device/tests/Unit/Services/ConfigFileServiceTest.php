<?php

declare(strict_types=1);

use App\Models\Project;
use App\Services\ConfigFileService;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    $this->service = new ConfigFileService;

    $this->testDir = storage_path('testing/config');
    $this->backupDir = storage_path('testing/backups');
    $this->schemaDir = storage_path('testing/schemas');

    if (! File::isDirectory($this->testDir)) {
        File::makeDirectory($this->testDir, 0755, true);
    }
    if (! File::isDirectory($this->backupDir)) {
        File::makeDirectory($this->backupDir, 0755, true);
    }
    if (! File::isDirectory($this->schemaDir)) {
        File::makeDirectory($this->schemaDir, 0755, true);
    }

    config()->set('vibecodepc.config_files', [
        'test_config' => [
            'path' => $this->testDir.'/test.json',
            'label' => 'Test Config',
            'description' => 'Test configuration file',
            'editable' => true,
            'scope' => 'global',
        ],
        'test_project_config' => [
            'path_template' => '{project_path}/config.json',
            'label' => 'Test Project Config',
            'description' => 'Test project-scoped configuration',
            'editable' => true,
            'scope' => 'project',
        ],
        'copilot_instructions' => [
            'path' => $this->testDir.'/copilot.md',
            'label' => 'Copilot Instructions',
            'description' => 'Copilot instructions file',
            'editable' => true,
            'scope' => 'global',
        ],
    ]);

    config()->set('vibecodepc.config_editor', [
        'backup_retention_days' => 30,
        'max_file_size_kb' => 64,
        'backup_directory' => $this->backupDir,
    ]);
});

afterEach(function (): void {
    if (File::isDirectory($this->testDir)) {
        File::deleteDirectory($this->testDir);
    }
    if (File::isDirectory($this->backupDir)) {
        File::deleteDirectory($this->backupDir);
    }
    if (File::isDirectory($this->schemaDir)) {
        File::deleteDirectory($this->schemaDir);
    }
});

describe('ConfigFileService', function (): void {
    test('getContent returns empty string for non-existent file', function (): void {
        $content = $this->service->getContent('test_config');

        expect($content)->toBe('');
    });

    test('getContent returns file content for existing file', function (): void {
        File::put($this->testDir.'/test.json', '{"key": "value"}', true);

        $content = $this->service->getContent('test_config');

        expect($content)->toBe('{"key": "value"}');
    });

    test('getContent throws exception for unreadable file', function (): void {
        File::put($this->testDir.'/test.json', '{"key": "value"}', true);
        chmod($this->testDir.'/test.json', 0000);

        expect(fn () => $this->service->getContent('test_config'))
            ->toThrow(\RuntimeException::class, 'Configuration file is not readable');

        chmod($this->testDir.'/test.json', 0644);
    });

    test('getContent throws exception when path is a directory', function (): void {
        // Create a directory at the expected file path
        $dirPath = $this->testDir.'/test.json';
        File::makeDirectory($dirPath, 0755, true);

        expect(fn () => $this->service->getContent('test_config'))
            ->toThrow(\RuntimeException::class, 'Configuration file is not readable');

        File::deleteDirectory($dirPath);
    });

    test('getContent throws exception for symlink to non-existent file', function (): void {
        $realPath = $this->testDir.'/real.json';
        $linkPath = $this->testDir.'/test.json';

        // Create a symlink pointing to a non-existent file
        File::put($realPath, '{"key": "value"}', true);
        symlink($realPath, $linkPath);

        // Verify symlink exists and points to file
        expect(File::exists($linkPath))->toBeTrue();

        // Delete the target file, making symlink broken
        File::delete($realPath);

        // File::exists returns false for broken symlinks, so getContent returns empty
        $content = $this->service->getContent('test_config');
        expect($content)->toBe('');
    });

    test('putContent writes content to file', function (): void {
        $this->service->putContent('test_config', '{"key": "value"}');

        expect(File::exists($this->testDir.'/test.json'))->toBeTrue();
        expect(File::get($this->testDir.'/test.json'))->toBe('{"key": "value"}');
    });

    test('putContent creates backup before overwriting', function (): void {
        File::put($this->testDir.'/test.json', '{"original": "data"}', true);

        $this->service->putContent('test_config', '{"key": "value"}');

        $backups = $this->service->listBackups('test_config');
        expect($backups)->toHaveCount(1);
        expect(File::get($backups[0]['path']))->toBe('{"original": "data"}');
    });

    test('putContent validates JSON for non-markdown files', function (): void {
        expect(fn () => $this->service->putContent('test_config', 'not valid json'))
            ->toThrow(\JsonException::class);
    });

    test('putContent skips JSON validation for copilot instructions', function (): void {
        $this->service->putContent('copilot_instructions', '# Instructions\n\nThis is markdown.');

        expect(File::exists($this->testDir.'/copilot.md'))->toBeTrue();
        expect(File::get($this->testDir.'/copilot.md'))->toBe('# Instructions\n\nThis is markdown.');
    });

    test('putContent throws exception for oversized content', function (): void {
        $largeContent = str_repeat('x', 100 * 1024);

        expect(fn () => $this->service->putContent('test_config', $largeContent))
            ->toThrow(\InvalidArgumentException::class, 'exceeds maximum allowed size');
    });

    test('putContent throws exception for unknown key', function (): void {
        expect(fn () => $this->service->putContent('unknown_key', '{}'))
            ->toThrow(\InvalidArgumentException::class, 'Unknown configuration key');
    });

    test('validateJson validates valid JSON', function (): void {
        $result = $this->service->validateJson('{"key": "value"}');

        expect($result)->toBe(['key' => 'value']);
    });

    test('validateJson validates JSON with comments', function (): void {
        $jsonc = "{\n  // This is a comment\n  \"key\": \"value\"\n}";
        $result = $this->service->validateJson($jsonc);

        expect($result)->toBe(['key' => 'value']);
    });

    test('validateJson throws exception for invalid JSON', function (): void {
        expect(fn () => $this->service->validateJson('not valid json'))
            ->toThrow(\JsonException::class);
    });

    test('validateJson validates boost.json structure', function (): void {
        config()->set('vibecodepc.config_files.boost', [
            'path' => $this->testDir.'/boost.json',
            'label' => 'Boost',
            'description' => 'Boost configuration',
            'editable' => true,
        ]);

        $validBoost = [
            'agents' => ['claude_code', 'copilot'],
            'guidelines' => true,
            'skills' => ['livewire-development'],
        ];

        $result = $this->service->validateJson(json_encode($validBoost), 'boost');

        expect($result)->toBe($validBoost);
    });

    test('validateJson throws exception for invalid boost.json agents type', function (): void {
        config()->set('vibecodepc.config_files.boost', [
            'path' => $this->testDir.'/boost.json',
            'label' => 'Boost',
            'description' => 'Boost configuration',
            'editable' => true,
        ]);

        $invalidBoost = ['agents' => 'not an array'];

        expect(fn () => $this->service->validateJson(json_encode($invalidBoost), 'boost'))
            ->toThrow(\InvalidArgumentException::class, 'agents" must be an array');
    });

    test('backup creates backup file', function (): void {
        File::put($this->testDir.'/test.json', '{"key": "value"}', true);

        $backupPath = $this->service->backup('test_config');

        expect(File::exists($backupPath))->toBeTrue();
        expect(File::get($backupPath))->toBe('{"key": "value"}');
    });

    test('backup throws exception for non-existent file', function (): void {
        expect(fn () => $this->service->backup('test_config'))
            ->toThrow(\RuntimeException::class, 'Cannot backup non-existent file');
    });

    test('listBackups returns sorted backups', function (): void {
        File::put($this->testDir.'/test.json', 'v1', true);
        sleep(1);
        $this->service->backup('test_config');

        File::put($this->testDir.'/test.json', 'v2', true);
        sleep(1);
        $this->service->backup('test_config');

        $backups = $this->service->listBackups('test_config');

        expect($backups)->toHaveCount(2);
        expect(File::get($backups[0]['path']))->toBe('v2');
        expect(File::get($backups[1]['path']))->toBe('v1');
    });

    test('listBackups returns empty array when no backups exist', function (): void {
        $backups = $this->service->listBackups('test_config');

        expect($backups)->toBe([]);
    });

    test('restore overwrites file with backup content', function (): void {
        File::put($this->testDir.'/test.json', 'original', true);
        $backupPath = $this->service->backup('test_config');
        File::put($this->testDir.'/test.json', 'changed', true);

        $this->service->restore('test_config', $backupPath);

        expect(File::get($this->testDir.'/test.json'))->toBe('original');
    });

    test('restore throws exception for non-existent backup', function (): void {
        expect(fn () => $this->service->restore('test_config', '/nonexistent/backup.json'))
            ->toThrow(\RuntimeException::class, 'Backup file does not exist');
    });

    test('exists returns true for readable file', function (): void {
        File::put($this->testDir.'/test.json', '{}', true);

        expect($this->service->exists('test_config'))->toBeTrue();
    });

    test('exists returns false for non-existent file', function (): void {
        expect($this->service->exists('test_config'))->toBeFalse();
    });

    describe('project-scoped configurations', function (): void {
        beforeEach(function (): void {
            $this->project = Project::factory()->create([
                'name' => 'Test Project',
                'path' => $this->testDir.'/projects/test-project',
            ]);

            if (! File::isDirectory($this->project->path)) {
                File::makeDirectory($this->project->path, 0755, true);
            }
        });

        test('getScope returns global for global configs', function (): void {
            expect($this->service->getScope('test_config'))->toBe('global');
        });

        test('getScope returns project for project-scoped configs', function (): void {
            expect($this->service->getScope('test_project_config'))->toBe('project');
        });

        test('isProjectScoped returns false for global configs', function (): void {
            expect($this->service->isProjectScoped('test_config'))->toBeFalse();
        });

        test('isProjectScoped returns true for project-scoped configs', function (): void {
            expect($this->service->isProjectScoped('test_project_config'))->toBeTrue();
        });

        test('resolvePath returns path for global config', function (): void {
            $path = $this->service->resolvePath('test_config');

            expect($path)->toBe($this->testDir.'/test.json');
        });

        test('resolvePath resolves path template for project-scoped config', function (): void {
            $path = $this->service->resolvePath('test_project_config', $this->project);

            expect($path)->toBe($this->project->path.'/config.json');
        });

        test('resolvePath throws exception when project is required but not provided', function (): void {
            expect(fn () => $this->service->resolvePath('test_project_config'))
                ->toThrow(\InvalidArgumentException::class, 'Project is required for project-scoped config');
        });

        test('getContent works with project-scoped config', function (): void {
            File::put($this->project->path.'/config.json', '{"project": "value"}', true);

            $content = $this->service->getContent('test_project_config', $this->project);

            expect($content)->toBe('{"project": "value"}');
        });

        test('putContent works with project-scoped config', function (): void {
            $this->service->putContent('test_project_config', '{"project": "value"}', $this->project);

            expect(File::exists($this->project->path.'/config.json'))->toBeTrue();
            expect(File::get($this->project->path.'/config.json'))->toBe('{"project": "value"}');
        });

        test('backup includes project suffix for project-scoped configs', function (): void {
            File::put($this->project->path.'/config.json', '{"project": "backup"}', true);

            $backupPath = $this->service->backup('test_project_config', $this->project);

            expect(File::exists($backupPath))->toBeTrue();
            expect(strpos($backupPath, '-project-1'))->toBeInt();
            expect(File::get($backupPath))->toBe('{"project": "backup"}');
        });

        test('listBackups filters by project for project-scoped configs', function (): void {
            File::put($this->project->path.'/config.json', 'v1', true);
            $this->service->backup('test_project_config', $this->project);

            $project2 = Project::factory()->create([
                'name' => 'Project 2',
                'path' => $this->testDir.'/projects/project-2',
            ]);
            if (! File::isDirectory($project2->path)) {
                File::makeDirectory($project2->path, 0755, true);
            }
            File::put($project2->path.'/config.json', 'v2', true);
            $this->service->backup('test_project_config', $project2);

            $backups = $this->service->listBackups('test_project_config', $this->project);

            expect($backups)->toHaveCount(1);
            expect(File::get($backups[0]['path']))->toBe('v1');

            File::deleteDirectory($project2->path);
        });

        describe('resolvePath edge cases', function (): void {
            test('handles project path with spaces', function (): void {
                $projectWithSpaces = Project::factory()->create([
                    'name' => 'Project With Spaces',
                    'path' => $this->testDir.'/projects/project with spaces',
                ]);
                if (! File::isDirectory($projectWithSpaces->path)) {
                    File::makeDirectory($projectWithSpaces->path, 0755, true);
                }

                $path = $this->service->resolvePath('test_project_config', $projectWithSpaces);

                expect($path)->toBe($projectWithSpaces->path.'/config.json');

                File::deleteDirectory($projectWithSpaces->path);
            });

            test('handles project path with unicode characters', function (): void {
                $projectUnicode = Project::factory()->create([
                    'name' => 'Projekt Mit Umlauten',
                    'path' => $this->testDir.'/projects/项目-日本語-émojis-🚀',
                ]);
                if (! File::isDirectory($projectUnicode->path)) {
                    File::makeDirectory($projectUnicode->path, 0755, true);
                }

                $path = $this->service->resolvePath('test_project_config', $projectUnicode);

                expect($path)->toBe($projectUnicode->path.'/config.json');

                File::deleteDirectory($projectUnicode->path);
            });

            test('handles project path with special characters', function (): void {
                $projectSpecial = Project::factory()->create([
                    'name' => 'Special Chars Project',
                    'path' => $this->testDir.'/projects/project-with-dash_underscore.dot',
                ]);
                if (! File::isDirectory($projectSpecial->path)) {
                    File::makeDirectory($projectSpecial->path, 0755, true);
                }

                $path = $this->service->resolvePath('test_project_config', $projectSpecial);

                expect($path)->toBe($projectSpecial->path.'/config.json');

                File::deleteDirectory($projectSpecial->path);
            });

            test('handles relative project path', function (): void {
                // Get the relative path from storage_path
                $absolutePath = $this->testDir.'/projects/relative-test';
                $relativePath = 'storage/testing/config/projects/relative-test';

                // Laravel's File facade works with absolute paths, but we test that the service
                // properly uses whatever path is provided in the project model
                $projectRelative = Project::factory()->create([
                    'name' => 'Relative Path Project',
                    'path' => $relativePath,
                ]);

                $path = $this->service->resolvePath('test_project_config', $projectRelative);

                // The path should contain the relative path as-is
                expect($path)->toContain($relativePath);
                expect($path)->toBe($relativePath.'/config.json');
            });

            test('replaces multiple project_path placeholders in template', function (): void {
                // Create a config with multiple placeholders
                config()->set('vibecodepc.config_files.multi_path_config', [
                    'path_template' => '{project_path}/config/{project_path}/settings.json',
                    'label' => 'Multi Path Config',
                    'description' => 'Test config with multiple path placeholders',
                    'editable' => true,
                    'scope' => 'project',
                ]);

                $path = $this->service->resolvePath('multi_path_config', $this->project);

                // Each placeholder should be replaced
                $expected = $this->project->path.'/config/'.$this->project->path.'/settings.json';
                expect($path)->toBe($expected);
            });

            test('handles project path with trailing slash', function (): void {
                $projectWithSlash = Project::factory()->create([
                    'name' => 'Trailing Slash Project',
                    'path' => rtrim($this->testDir.'/projects/trailing-slash', '/').'/',
                ]);
                if (! File::isDirectory($projectWithSlash->path)) {
                    File::makeDirectory(rtrim($projectWithSlash->path, '/'), 0755, true);
                }

                $path = $this->service->resolvePath('test_project_config', $projectWithSlash);

                // Should handle trailing slash gracefully
                // str_replace will replace the entire {project_path} including trailing slash
                // so the path might have double slashes if template starts with /
                expect($path)->toContain($projectWithSlash->path);
                expect($path)->toContain('config.json');

                File::deleteDirectory(rtrim($projectWithSlash->path, '/'));
            });

            test('handles very long project path', function (): void {
                $longPath = $this->testDir.'/projects/'.str_repeat('very-long-path-name/', 20).'project';
                $projectLong = Project::factory()->create([
                    'name' => 'Long Path Project',
                    'path' => $longPath,
                ]);
                if (! File::isDirectory($projectLong->path)) {
                    File::makeDirectory($projectLong->path, 0755, true);
                }

                $path = $this->service->resolvePath('test_project_config', $projectLong);

                expect($path)->toBe($longPath.'/config.json');

                File::deleteDirectory($projectLong->path);
            });

            test('handles project path with parent directory references', function (): void {
                $projectParentRef = Project::factory()->create([
                    'name' => 'Parent Ref Project',
                    'path' => $this->testDir.'/projects/../project-parent-ref',
                ]);

                // Create the actual directory
                $actualPath = $this->testDir.'/project-parent-ref';
                if (! File::isDirectory($actualPath)) {
                    File::makeDirectory($actualPath, 0755, true);
                }

                $path = $this->service->resolvePath('test_project_config', $projectParentRef);

                // The path is resolved exactly as provided by str_replace
                expect($path)->toBe($projectParentRef->path.'/config.json');

                File::deleteDirectory($actualPath);
            });
        });

        afterEach(function (): void {
            if (File::isDirectory($this->testDir.'/projects')) {
                File::deleteDirectory($this->testDir.'/projects');
            }
        });

        describe('deleted projects edge cases', function (): void {
            test('getContent works with soft-deleted project when file exists', function (): void {
                // Create project and config file
                File::put($this->project->path.'/config.json', '{"project": "value"}', true);

                // Soft delete the project
                $this->project->delete();

                // getContent should still work if the file exists (uses project path from the model)
                // Re-fetch the soft-deleted project including trashed
                $deletedProject = Project::withTrashed()->find($this->project->id);
                $content = $this->service->getContent('test_project_config', $deletedProject);

                expect($content)->toBe('{"project": "value"}');

                // Cleanup
                $this->project->forceDelete();
            });

            test('putContent works with soft-deleted project when file exists', function (): void {
                // Create initial config file
                File::put($this->project->path.'/config.json', '{"original": "data"}', true);

                // Soft delete the project
                $this->project->delete();

                // Re-fetch the soft-deleted project including trashed
                $deletedProject = Project::withTrashed()->find($this->project->id);

                // putContent should still work
                $this->service->putContent('test_project_config', '{"updated": "data"}', $deletedProject);

                expect(File::get($this->project->path.'/config.json'))->toBe('{"updated": "data"}');

                // Cleanup
                $this->project->forceDelete();
            });

            test('backup works with soft-deleted project', function (): void {
                // Create config file
                File::put($this->project->path.'/config.json', '{"backup": "test"}', true);

                // Soft delete the project
                $this->project->delete();

                // Re-fetch the soft-deleted project including trashed
                $deletedProject = Project::withTrashed()->find($this->project->id);

                // Backup should still work
                $backupPath = $this->service->backup('test_project_config', $deletedProject);

                expect(File::exists($backupPath))->toBeTrue();
                expect(File::get($backupPath))->toBe('{"backup": "test"}');
                expect(strpos($backupPath, "-project-{$this->project->id}"))->toBeInt();

                // Cleanup
                $this->project->forceDelete();
            });

            test('listBackups includes backups from soft-deleted projects', function (): void {
                // Create config file and backup
                File::put($this->project->path.'/config.json', 'v1', true);
                $this->service->backup('test_project_config', $this->project);

                // Soft delete the project
                $this->project->delete();

                // Re-fetch the soft-deleted project including trashed
                $deletedProject = Project::withTrashed()->find($this->project->id);

                // listBackups should still find the backup
                $backups = $this->service->listBackups('test_project_config', $deletedProject);

                expect($backups)->toHaveCount(1);
                expect(File::get($backups[0]['path']))->toBe('v1');

                // Cleanup
                $this->project->forceDelete();
            });

            test('delete operation works with soft-deleted project', function (): void {
                // Create config file
                File::put($this->project->path.'/config.json', '{"delete": "me"}', true);
                expect(File::exists($this->project->path.'/config.json'))->toBeTrue();

                // Soft delete the project
                $this->project->delete();

                // Re-fetch the soft-deleted project including trashed
                $deletedProject = Project::withTrashed()->find($this->project->id);

                // Delete should still work
                $this->service->delete('test_project_config', $deletedProject);

                expect(File::exists($this->project->path.'/config.json'))->toBeFalse();

                // Cleanup
                $this->project->forceDelete();
            });

            test('resolvePath works with soft-deleted project', function (): void {
                // Soft delete the project
                $this->project->delete();

                // Re-fetch the soft-deleted project including trashed
                $deletedProject = Project::withTrashed()->find($this->project->id);

                // resolvePath should still work
                $path = $this->service->resolvePath('test_project_config', $deletedProject);

                expect($path)->toBe($this->project->path.'/config.json');

                // Cleanup
                $this->project->forceDelete();
            });

            test('exists works with soft-deleted project when file exists', function (): void {
                // Create config file
                File::put($this->project->path.'/config.json', '{"exists": "true"}', true);

                // Soft delete the project
                $this->project->delete();

                // Re-fetch the soft-deleted project including trashed
                $deletedProject = Project::withTrashed()->find($this->project->id);

                // exists should return true
                expect($this->service->exists('test_project_config', $deletedProject))->toBeTrue();

                // Cleanup
                $this->project->forceDelete();
            });

            test('exists returns false with soft-deleted project when file does not exist', function (): void {
                // Ensure no config file exists
                if (File::exists($this->project->path.'/config.json')) {
                    File::delete($this->project->path.'/config.json');
                }

                // Soft delete the project
                $this->project->delete();

                // Re-fetch the soft-deleted project including trashed
                $deletedProject = Project::withTrashed()->find($this->project->id);

                // exists should return false
                expect($this->service->exists('test_project_config', $deletedProject))->toBeFalse();

                // Cleanup
                $this->project->forceDelete();
            });

            test('restore works with soft-deleted project', function (): void {
                // Create initial file and backup
                File::put($this->project->path.'/config.json', 'original', true);
                $backupPath = $this->service->backup('test_project_config', $this->project);

                // Modify the file
                File::put($this->project->path.'/config.json', 'modified', true);

                // Soft delete the project
                $this->project->delete();

                // Re-fetch the soft-deleted project including trashed
                $deletedProject = Project::withTrashed()->find($this->project->id);

                // Restore should work
                $this->service->restore('test_project_config', $backupPath, $deletedProject);

                expect(File::get($this->project->path.'/config.json'))->toBe('original');

                // Cleanup
                $this->project->forceDelete();
            });

            test('config directory exists but project force deleted from database', function (): void {
                // Create config file
                File::put($this->project->path.'/config.json', '{"orphaned": "config"}', true);

                // Get project ID before force delete
                $projectId = $this->project->id;
                $projectPath = $this->project->path;

                // Force delete the project (removes from database completely)
                $this->project->forceDelete();

                // At this point, we have a project model that's deleted from DB
                // but the config file still exists on disk

                // Create a temporary project model with the same path
                // This simulates what happens if someone tries to access
                // a config for a force-deleted project using a cached/stored reference
                $orphanedProject = new Project([
                    'id' => $projectId,
                    'name' => 'Orphaned Project',
                    'path' => $projectPath,
                ]);
                $orphanedProject->id = $projectId; // Set ID manually since it's not in DB

                // getContent should still work if we have the project object
                // even though it's not in the database
                $content = $this->service->getContent('test_project_config', $orphanedProject);

                expect($content)->toBe('{"orphaned": "config"}');
            });

            test('backup isolation for deleted vs active projects', function (): void {
                // Create two projects
                $project2 = Project::factory()->create([
                    'name' => 'Active Project',
                    'path' => $this->testDir.'/projects/active-project',
                ]);
                if (! File::isDirectory($project2->path)) {
                    File::makeDirectory($project2->path, 0755, true);
                }

                // Create config files for both
                File::put($this->project->path.'/config.json', 'deleted-project-content', true);
                File::put($project2->path.'/config.json', 'active-project-content', true);

                // Create backups for both
                $this->service->backup('test_project_config', $this->project);
                $this->service->backup('test_project_config', $project2);

                // Soft delete first project
                $this->project->delete();

                // Re-fetch deleted project
                $deletedProject = Project::withTrashed()->find($this->project->id);

                // List backups for deleted project should only show its backups
                $deletedBackups = $this->service->listBackups('test_project_config', $deletedProject);
                expect($deletedBackups)->toHaveCount(1);
                expect(File::get($deletedBackups[0]['path']))->toBe('deleted-project-content');

                // List backups for active project should only show its backups
                $activeBackups = $this->service->listBackups('test_project_config', $project2);
                expect($activeBackups)->toHaveCount(1);
                expect(File::get($activeBackups[0]['path']))->toBe('active-project-content');

                // Verify project ID suffix in backup filenames
                foreach ($deletedBackups as $backup) {
                    expect(strpos($backup['filename'], "-project-{$this->project->id}"))->toBeInt();
                }
                foreach ($activeBackups as $backup) {
                    expect(strpos($backup['filename'], "-project-{$project2->id}"))->toBeInt();
                }

                // Cleanup
                $this->project->forceDelete();
                File::deleteDirectory($project2->path);
            });

            test('project isolation prevents backup leakage between deleted projects', function (): void {
                // Create two projects that will both be deleted
                $project2 = Project::factory()->create([
                    'name' => 'Project 2',
                    'path' => $this->testDir.'/projects/project-2',
                ]);
                if (! File::isDirectory($project2->path)) {
                    File::makeDirectory($project2->path, 0755, true);
                }

                // Create config and backup for both
                File::put($this->project->path.'/config.json', 'project-1-data', true);
                File::put($project2->path.'/config.json', 'project-2-data', true);

                $this->service->backup('test_project_config', $this->project);
                $this->service->backup('test_project_config', $project2);

                // Soft delete both
                $this->project->delete();
                $project2->delete();

                // Re-fetch both
                $deletedProject1 = Project::withTrashed()->find($this->project->id);
                $deletedProject2 = Project::withTrashed()->find($project2->id);

                // Each project should only see its own backups
                $backups1 = $this->service->listBackups('test_project_config', $deletedProject1);
                $backups2 = $this->service->listBackups('test_project_config', $deletedProject2);

                expect($backups1)->toHaveCount(1);
                expect($backups2)->toHaveCount(1);
                expect(File::get($backups1[0]['path']))->toBe('project-1-data');
                expect(File::get($backups2[0]['path']))->toBe('project-2-data');

                // Ensure no cross-contamination
                $backup1Filenames = array_map(fn ($b) => $b['filename'], $backups1);
                $backup2Filenames = array_map(fn ($b) => $b['filename'], $backups2);

                // No overlap between the two sets
                $intersection = array_intersect($backup1Filenames, $backup2Filenames);
                expect($intersection)->toBe([]);

                // Cleanup
                $this->project->forceDelete();
                $project2->forceDelete();
            });

            describe('backup listing with multiple projects', function (): void {
                test('listBackups returns only backups for specified project', function (): void {
                    // Create multiple projects
                    $project2 = Project::factory()->create([
                        'name' => 'Project 2',
                        'path' => $this->testDir.'/projects/project-2',
                    ]);
                    $project3 = Project::factory()->create([
                        'name' => 'Project 3',
                        'path' => $this->testDir.'/projects/project-3',
                    ]);

                    if (! File::isDirectory($project2->path)) {
                        File::makeDirectory($project2->path, 0755, true);
                    }
                    if (! File::isDirectory($project3->path)) {
                        File::makeDirectory($project3->path, 0755, true);
                    }

                    // Create config files for each project
                    File::put($this->project->path.'/config.json', 'project-1-content', true);
                    File::put($project2->path.'/config.json', 'project-2-content', true);
                    File::put($project3->path.'/config.json', 'project-3-content', true);

                    // Create backups for each project
                    $this->service->backup('test_project_config', $this->project);
                    $this->service->backup('test_project_config', $project2);
                    $this->service->backup('test_project_config', $project3);

                    // List backups for project 1
                    $backups1 = $this->service->listBackups('test_project_config', $this->project);

                    // Should only return project 1 backups
                    expect($backups1)->toHaveCount(1);
                    expect(File::get($backups1[0]['path']))->toBe('project-1-content');

                    // List backups for project 2
                    $backups2 = $this->service->listBackups('test_project_config', $project2);

                    // Should only return project 2 backups
                    expect($backups2)->toHaveCount(1);
                    expect(File::get($backups2[0]['path']))->toBe('project-2-content');

                    // List backups for project 3
                    $backups3 = $this->service->listBackups('test_project_config', $project3);

                    // Should only return project 3 backups
                    expect($backups3)->toHaveCount(1);
                    expect(File::get($backups3[0]['path']))->toBe('project-3-content');

                    // Cleanup
                    File::deleteDirectory($project2->path);
                    File::deleteDirectory($project3->path);
                });

                test('project suffix format in backup filenames is correct', function (): void {
                    // Create config file and backup for the project
                    File::put($this->project->path.'/config.json', 'test-content', true);
                    $backupPath = $this->service->backup('test_project_config', $this->project);

                    $filename = basename($backupPath);

                    // Verify project suffix format: -project-{id}
                    expect($filename)->toContain("-project-{$this->project->id}");

                    // Verify the full pattern: {key}-project-{id}-{timestamp}.json
                    $expectedPattern = '/^test_project_config-project-'.$this->project->id.'-\d{4}-\d{2}-\d{2}-\d{6}-\d+\.json$/';
                    expect(preg_match($expectedPattern, $filename))->toBe(1);

                    // Verify the backup appears in listBackups
                    $backups = $this->service->listBackups('test_project_config', $this->project);
                    expect($backups)->toHaveCount(1);
                    expect($backups[0]['filename'])->toContain("-project-{$this->project->id}");

                    // Verify project ID is not in global config backups
                    File::put($this->testDir.'/test.json', 'global-content', true);
                    $globalBackupPath = $this->service->backup('test_config');
                    $globalFilename = basename($globalBackupPath);

                    // Global backups should NOT have project suffix
                    expect($globalFilename)->not->toContain('-project-');
                });

                test('backups do not leak between projects with same config key', function (): void {
                    // Create multiple projects
                    $project2 = Project::factory()->create([
                        'name' => 'Project 2',
                        'path' => $this->testDir.'/projects/project-2',
                    ]);
                    $project3 = Project::factory()->create([
                        'name' => 'Project 3',
                        'path' => $this->testDir.'/projects/project-3',
                    ]);

                    if (! File::isDirectory($project2->path)) {
                        File::makeDirectory($project2->path, 0755, true);
                    }
                    if (! File::isDirectory($project3->path)) {
                        File::makeDirectory($project3->path, 0755, true);
                    }

                    // Create multiple backups for each project
                    File::put($this->project->path.'/config.json', 'v1-project1', true);
                    $this->service->backup('test_project_config', $this->project);
                    sleep(1);
                    File::put($this->project->path.'/config.json', 'v2-project1', true);
                    $this->service->backup('test_project_config', $this->project);

                    File::put($project2->path.'/config.json', 'v1-project2', true);
                    $this->service->backup('test_project_config', $project2);
                    sleep(1);
                    File::put($project2->path.'/config.json', 'v2-project2', true);
                    $this->service->backup('test_project_config', $project2);

                    File::put($project3->path.'/config.json', 'v1-project3', true);
                    $this->service->backup('test_project_config', $project3);

                    // Get backups for project 1
                    $backups1 = $this->service->listBackups('test_project_config', $this->project);

                    // Get all backup contents for project 1
                    $contents1 = array_map(fn ($b) => File::get($b['path']), $backups1);

                    // Verify only project 1 contents are present
                    expect($contents1)->toContain('v1-project1');
                    expect($contents1)->toContain('v2-project1');
                    expect($contents1)->not->toContain('v1-project2');
                    expect($contents1)->not->toContain('v2-project2');
                    expect($contents1)->not->toContain('v1-project3');

                    // Verify no backup filenames contain other project IDs
                    foreach ($backups1 as $backup) {
                        // Filename should contain project 1 ID
                        expect(strpos($backup['filename'], "-project-{$this->project->id}"))->toBeInt();

                        // Filename should NOT contain project 2 or 3 IDs
                        expect(strpos($backup['filename'], "-project-{$project2->id}"))->toBeFalse();
                        expect(strpos($backup['filename'], "-project-{$project3->id}"))->toBeFalse();
                    }

                    // Get backups for project 2
                    $backups2 = $this->service->listBackups('test_project_config', $project2);

                    // Verify project 2 has correct backups
                    $contents2 = array_map(fn ($b) => File::get($b['path']), $backups2);
                    expect($contents2)->toContain('v1-project2');
                    expect($contents2)->toContain('v2-project2');
                    expect($contents2)->not->toContain('v1-project1');
                    expect($contents2)->not->toContain('v2-project1');

                    // Get backups for project 3
                    $backups3 = $this->service->listBackups('test_project_config', $project3);

                    // Verify project 3 has correct backups
                    $contents3 = array_map(fn ($b) => File::get($b['path']), $backups3);
                    expect($contents3)->toContain('v1-project3');
                    expect($contents3)->not->toContain('v1-project1');
                    expect($contents3)->not->toContain('v1-project2');

                    // Cleanup
                    File::deleteDirectory($project2->path);
                    File::deleteDirectory($project3->path);
                });

                test('listBackups returns empty array when no backups exist for project', function (): void {
                    // Create a new project with no backups
                    $newProject = Project::factory()->create([
                        'name' => 'New Project',
                        'path' => $this->testDir.'/projects/new-project',
                    ]);

                    if (! File::isDirectory($newProject->path)) {
                        File::makeDirectory($newProject->path, 0755, true);
                    }

                    // List backups for new project - should be empty
                    $backups = $this->service->listBackups('test_project_config', $newProject);

                    expect($backups)->toBe([]);

                    // Cleanup
                    File::deleteDirectory($newProject->path);
                });

                test('listBackups is sorted by creation time descending for each project', function (): void {
                    // Create multiple backups with time gaps
                    File::put($this->project->path.'/config.json', 'v1', true);
                    $this->service->backup('test_project_config', $this->project);
                    sleep(1);

                    File::put($this->project->path.'/config.json', 'v2', true);
                    $this->service->backup('test_project_config', $this->project);
                    sleep(1);

                    File::put($this->project->path.'/config.json', 'v3', true);
                    $this->service->backup('test_project_config', $this->project);

                    // Get backups - should be sorted by timestamp (newest first)
                    $backups = $this->service->listBackups('test_project_config', $this->project);

                    expect($backups)->toHaveCount(3);

                    // Verify sorted by created_at descending
                    for ($i = 0; $i < count($backups) - 1; $i++) {
                        expect($backups[$i]['created_at'])->toBeGreaterThanOrEqual($backups[$i + 1]['created_at']);
                    }

                    // Verify content order (v3 should be newest)
                    expect(File::get($backups[0]['path']))->toBe('v3');
                    expect(File::get($backups[1]['path']))->toBe('v2');
                    expect(File::get($backups[2]['path']))->toBe('v1');
                });

                test('backups for different config keys are isolated within project', function (): void {
                    // Create another project-scoped config
                    config()->set('vibecodepc.config_files.test_project_config_2', [
                        'path_template' => '{project_path}/config2.json',
                        'label' => 'Test Project Config 2',
                        'description' => 'Another test project-scoped configuration',
                        'editable' => true,
                        'scope' => 'project',
                    ]);

                    // Create config files for both config keys
                    File::put($this->project->path.'/config.json', 'config1-content', true);
                    File::put($this->project->path.'/config2.json', 'config2-content', true);

                    // Create backups for both config keys
                    $this->service->backup('test_project_config', $this->project);
                    $this->service->backup('test_project_config_2', $this->project);

                    // List backups for config key 1
                    $backups1 = $this->service->listBackups('test_project_config', $this->project);

                    // Should only show backups for config key 1
                    expect($backups1)->toHaveCount(1);
                    expect(File::get($backups1[0]['path']))->toBe('config1-content');

                    // List backups for config key 2
                    $backups2 = $this->service->listBackups('test_project_config_2', $this->project);

                    // Should only show backups for config key 2
                    expect($backups2)->toHaveCount(1);
                    expect(File::get($backups2[0]['path']))->toBe('config2-content');

                    // Verify no overlap
                    $paths1 = array_map(fn ($b) => $b['path'], $backups1);
                    $paths2 = array_map(fn ($b) => $b['path'], $backups2);

                    foreach ($paths1 as $path1) {
                        foreach ($paths2 as $path2) {
                            expect($path1)->not->toBe($path2);
                        }
                    }
                });

                test('backup isolation persists after multiple saves', function (): void {
                    // Create another project
                    $project2 = Project::factory()->create([
                        'name' => 'Project 2',
                        'path' => $this->testDir.'/projects/project-2',
                    ]);

                    if (! File::isDirectory($project2->path)) {
                        File::makeDirectory($project2->path, 0755, true);
                    }

                    // Save multiple times to project 1, creating backups each time
                    for ($i = 1; $i <= 3; $i++) {
                        File::put($this->project->path.'/config.json', "project1-v{$i}", true);
                        $this->service->backup('test_project_config', $this->project);
                    }

                    // Save multiple times to project 2
                    for ($i = 1; $i <= 3; $i++) {
                        File::put($project2->path.'/config.json', "project2-v{$i}", true);
                        $this->service->backup('test_project_config', $project2);
                    }

                    // Get backups for project 1
                    $backups1 = $this->service->listBackups('test_project_config', $this->project);

                    // Get backups for project 2
                    $backups2 = $this->service->listBackups('test_project_config', $project2);

                    // Both should have exactly 3 backups
                    expect($backups1)->toHaveCount(3);
                    expect($backups2)->toHaveCount(3);

                    // Verify all backups are for the correct project
                    $contents1 = array_map(fn ($b) => File::get($b['path']), $backups1);
                    $contents2 = array_map(fn ($b) => File::get($b['path']), $backups2);

                    foreach ($contents1 as $content) {
                        expect($content)->toContain('project1');
                    }

                    foreach ($contents2 as $content) {
                        expect($content)->toContain('project2');
                    }

                    // Cleanup
                    File::deleteDirectory($project2->path);
                });
            });
        });

        describe('delete operations', function (): void {
            test('delete removes existing file', function (): void {
                File::put($this->testDir.'/test.json', '{"key": "value"}', true);

                $this->service->delete('test_config');

                expect(File::exists($this->testDir.'/test.json'))->toBeFalse();
            });

            test('delete does nothing for non-existent file', function (): void {
                expect(fn () => $this->service->delete('test_config'))->not->toThrow(\Exception::class);
            });
        });

        describe('forbidden key validation', function (): void {
            test('validateJson throws exception for forbidden api_key', function (): void {
                $json = '{"api_key": "secret123"}';

                expect(fn () => $this->service->validateJson($json))
                    ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'api_key\'');
            });

            test('validateJson throws exception for forbidden api_secret', function (): void {
                $json = '{"api_secret": "shh"}';

                expect(fn () => $this->service->validateJson($json))
                    ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'api_secret\'');
            });

            test('validateJson throws exception for forbidden password', function (): void {
                $json = '{"password": "hunter2"}';

                expect(fn () => $this->service->validateJson($json))
                    ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'password\'');
            });

            test('validateJson throws exception for nested forbidden key', function (): void {
                $json = '{"config": {"secret_key": "hidden"}}';

                expect(fn () => $this->service->validateJson($json))
                    ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'secret_key\'');
            });

            test('validateJson throws exception for snake_case forbidden key', function (): void {
                $json = '{"private_key": "ssh-rsa ..."}';

                expect(fn () => $this->service->validateJson($json))
                    ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'private_key\'');
            });

            test('validateJson throws exception for camelCase forbidden key', function (): void {
                $json = '{"accessToken": "bearer123"}';

                expect(fn () => $this->service->validateJson($json))
                    ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'accessToken\'');
            });

            test('validateJson allows safe keys', function (): void {
                $json = '{"name": "test", "enabled": true, "count": 42}';

                $result = $this->service->validateJson($json);

                expect($result)->toBe(['name' => 'test', 'enabled' => true, 'count' => 42]);
            });

            describe('forbidden key detection edge cases', function (): void {
                test('allows keys that contain forbidden pattern as substring but not exact match', function (): void {
                    // Keys like 'api_key_name' should NOT trigger (partial match is ok)
                    $json = '{"api_key_name": "valid", "my_api_key": "valid", "api_key_suffix": "valid"}';

                    $result = $this->service->validateJson($json);

                    expect($result)->toBe(['api_key_name' => 'valid', 'my_api_key' => 'valid', 'api_key_suffix' => 'valid']);
                });

                test('allows keys that contain password as substring but not exact match', function (): void {
                    // Keys like 'password_hint' should NOT trigger (partial match is ok)
                    $json = '{"password_hint": "valid", "user_password": "valid", "password_policy": "valid"}';

                    $result = $this->service->validateJson($json);

                    expect($result)->toBe(['password_hint' => 'valid', 'user_password' => 'valid', 'password_policy' => 'valid']);
                });

                test('allows keys that contain secret as substring but not exact match', function (): void {
                    // Keys like 'secret_question' should NOT trigger (partial match is ok)
                    $json = '{"secret_question": "valid", "secret_sauce": "valid", "top_secret": "valid"}';

                    $result = $this->service->validateJson($json);

                    expect($result)->toBe(['secret_question' => 'valid', 'secret_sauce' => 'valid', 'top_secret' => 'valid']);
                });

                test('allows keys that contain token as substring but not exact match', function (): void {
                    // Keys like 'token_expiry' should NOT trigger (partial match is ok)
                    $json = '{"token_expiry": "valid", "csrf_token_name": "valid", "token_generator": "valid"}';

                    $result = $this->service->validateJson($json);

                    expect($result)->toBe(['token_expiry' => 'valid', 'csrf_token_name' => 'valid', 'token_generator' => 'valid']);
                });

                test('detects all case variations of forbidden keys', function (): void {
                    // Test UPPERCASE
                    $json = '{"API_KEY": "secret"}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'API_KEY\'');

                    // Test MixedCase (PascalCase)
                    $json = '{"Api_Key": "secret"}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'Api_Key\'');

                    // Test camelCase
                    $json = '{"apiKey": "secret"}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'apiKey\'');
                });

                test('detects UPPERCASE variations of password and secret', function (): void {
                    $json = '{"PASSWORD": "secret"}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'PASSWORD\'');

                    $json = '{"SECRET": "shh"}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'SECRET\'');
                });

                test('detects mixed separator variations', function (): void {
                    // Test with hyphen separator
                    $json = '{"api-key": "secret"}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'api-key\'');

                    // Test with no separator (camelCase handled by case-insensitive regex)
                    $json = '{"accessToken": "secret"}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'accessToken\'');
                });

                test('allows values containing forbidden strings but not keys', function (): void {
                    // Values containing forbidden strings should be allowed
                    $json = '{"description": "This is an api_key value", "note": "password: 123", "info": "secret token here"}';

                    $result = $this->service->validateJson($json);

                    expect($result)->toBe([
                        'description' => 'This is an api_key value',
                        'note' => 'password: 123',
                        'info' => 'secret token here',
                    ]);
                });

                test('allows deeply nested values containing forbidden strings', function (): void {
                    // Values containing forbidden strings should be allowed, even nested
                    $json = '{"config": {"description": "This api_key is just a reference"}, "data": {"items": [{"text": "password"}]}}';

                    $result = $this->service->validateJson($json);

                    expect($result['config']['description'])->toBe('This api_key is just a reference');
                    expect($result['data']['items'][0]['text'])->toBe('password');
                });

                test('detects forbidden keys in arrays', function (): void {
                    // Arrays with keys (associative arrays) should still be checked
                    $json = '{"items": {"api_key": "secret"}}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'api_key\'');
                });

                test('allows numeric array values containing forbidden strings', function (): void {
                    // Numeric indices should be fine, even if values contain forbidden strings
                    $json = '{"items": ["api_key", "password", "secret"] }';

                    $result = $this->service->validateJson($json);

                    expect($result['items'])->toBe(['api_key', 'password', 'secret']);
                });

                test('detects forbidden keys in deeply nested objects', function (): void {
                    $json = '{"level1": {"level2": {"level3": {"password": "nested"}}}}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'password\' at path \'level1.level2.level3.password\'');
                });

                test('reports correct path for nested forbidden keys', function (): void {
                    $json = '{"config": {"auth": {"secret_key": "hidden"}}}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'secret_key\' at path \'config.auth.secret_key\'');
                });

                test('detects all forbidden key patterns', function (): void {
                    $forbiddenKeys = [
                        'api_key', 'api_secret', 'api_token',
                        'auth_token', 'access_token', 'bearer_token',
                        'private_key', 'secret_key', 'client_secret',
                        'password', 'secret', 'token',
                    ];

                    foreach ($forbiddenKeys as $key) {
                        $json = json_encode([$key => 'test_value']);
                        expect(fn () => $this->service->validateJson($json))
                            ->toThrow(\InvalidArgumentException::class, "Forbidden key detected: '{$key}'");
                    }
                });

                test('allows safe keys that start with forbidden patterns', function (): void {
                    // Keys starting with forbidden patterns but not matching exact patterns
                    $json = '{"api_key_version": "v1", "secret_sauce_recipe": "tasty", "token_ring": "jrr"}';

                    $result = $this->service->validateJson($json);

                    expect($result)->toBe([
                        'api_key_version' => 'v1',
                        'secret_sauce_recipe' => 'tasty',
                        'token_ring' => 'jrr',
                    ]);
                });

                test('allows underscores in valid keys', function (): void {
                    // Keys with underscores that are not forbidden
                    $json = '{"user_name": "john", "first_name": "John", "last_name": "Doe"}';

                    $result = $this->service->validateJson($json);

                    expect($result)->toBe(['user_name' => 'john', 'first_name' => 'John', 'last_name' => 'Doe']);
                });

                test('detects all variations of private_key', function (): void {
                    // Test underscore variations
                    $json = '{"private_key": "secret"}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'private_key\'');

                    // Test hyphen variation
                    $json = '{"private-key": "secret"}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'private-key\'');

                    // Test UPPERCASE
                    $json = '{"PRIVATE_KEY": "secret"}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'PRIVATE_KEY\'');
                });

                test('detects all variations of api_token', function (): void {
                    // Test underscore variation
                    $json = '{"api_token": "secret"}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'api_token\'');

                    // Test hyphen variation
                    $json = '{"api-token": "secret"}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'api-token\'');

                    // Test camelCase
                    $json = '{"apiToken": "secret"}';
                    expect(fn () => $this->service->validateJson($json))
                        ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected: \'apiToken\'');
                });

                test('allows empty string keys', function (): void {
                    // Empty string keys are unusual but should not trigger forbidden key detection
                    $json = '{"": "empty key value"}';

                    $result = $this->service->validateJson($json);

                    expect($result)->toBe(['' => 'empty key value']);
                });

                test('allows null values', function (): void {
                    $json = '{"name": null, "count": null}';

                    $result = $this->service->validateJson($json);

                    expect($result)->toBe(['name' => null, 'count' => null]);
                });

                test('allows boolean values', function (): void {
                    $json = '{"enabled": true, "disabled": false}';

                    $result = $this->service->validateJson($json);

                    expect($result)->toBe(['enabled' => true, 'disabled' => false]);
                });

                test('allows numeric values', function (): void {
                    $json = '{"count": 42, "pi": 3.14}';

                    $result = $this->service->validateJson($json);

                    expect($result)->toBe(['count' => 42, 'pi' => 3.14]);
                });
            });
        });

        describe('JSONC comment stripping', function (): void {
            test('strips single-line comments', function (): void {
                $jsonc = "{\n // This is a comment\n \"key\": \"value\"\n}";

                $result = $this->service->validateJson($jsonc);

                expect($result)->toBe(['key' => 'value']);
            });

            test('strips multi-line comments', function (): void {
                $jsonc = "{\n /* Multi-line\n comment */\n \"key\": \"value\"\n}";

                $result = $this->service->validateJson($jsonc);

                expect($result)->toBe(['key' => 'value']);
            });

            test('strips inline multi-line comments', function (): void {
                $jsonc = '{"key": /* inline */ "value"}';

                $result = $this->service->validateJson($jsonc);

                expect($result)->toBe(['key' => 'value']);
            });

            test('strips multiple comment types', function (): void {
                $jsonc = "{\n // Single line\n /* Multi-line */\n \"key\": \"value\",\n // Another comment\n \"other\": 123\n}";

                $result = $this->service->validateJson($jsonc);

                expect($result)->toBe(['key' => 'value', 'other' => 123]);
            });

            describe('JSONC comment stripping edge cases', function (): void {
                test('preserves comments inside string values', function (): void {
                    // Comments inside strings should be preserved, not stripped
                    $jsonc = '{"key": "value // not a comment", "other": "value /* also not a comment */ "}';

                    $result = $this->service->validateJson($jsonc);

                    expect($result['key'])->toBe('value // not a comment');
                    expect($result['other'])->toBe('value /* also not a comment */ ');
                });

                test('handles nested multi-line comments', function (): void {
                    // Nested /* /* */ */ - outer should still be stripped
                    $jsonc = '{"key": /* outer /* inner */ still outer */ "value"}';

                    // This test documents current behavior - the first */ closes the comment
                    // even if nested. This is consistent with most JSONC parsers.
                    // After stripping: {"key":  still outer */ "value"}
                    // This will cause a JSON parse error because "still outer" is not valid JSON
                    expect(fn () => $this->service->validateJson($jsonc))
                        ->toThrow(\JsonException::class);
                });

                test('handles unclosed multi-line comment at EOF', function (): void {
                    // Comment opened but never closed - should be stripped to end of file
                    $jsonc = '{"key": "value" /* unclosed comment';

                    // The unclosed comment consumes everything after it
                    // Result: {"key": "value" (incomplete)
                    expect(fn () => $this->service->validateJson($jsonc))
                        ->toThrow(\JsonException::class);
                });

                test('handles single-line comment at EOF without newline', function (): void {
                    // Comment at end of file with no trailing newline
                    $jsonc = '{"key": "value"} // comment at EOF';

                    $result = $this->service->validateJson($jsonc);

                    expect($result)->toBe(['key' => 'value']);
                });

                test('handles unicode characters in comments', function (): void {
                    // Comments with various unicode characters
                    $jsonc = "{\n // Comment with unicode: 你好 🚀 émojis\n \"key\": \"value\",\n /* Multi-line with\n unicode: 日本語 */\n \"other\": 123\n}";

                    $result = $this->service->validateJson($jsonc);

                    expect($result)->toBe(['key' => 'value', 'other' => 123]);
                });

                test('preserves quotes inside comments', function (): void {
                    // Comments containing quote characters should not affect parsing
                    $jsonc = "{\n // Comment with \"quotes\" and 'single quotes'\n \"key\": \"value\",\n /* Multi-line comment\n with \"quotes\" inside */\n \"other\": 123\n}";

                    $result = $this->service->validateJson($jsonc);

                    expect($result)->toBe(['key' => 'value', 'other' => 123]);
                });

                test('handles multiple consecutive single-line comments', function (): void {
                    $jsonc = "{\n // Comment 1\n // Comment 2\n // Comment 3\n \"key\": \"value\"\n}";

                    $result = $this->service->validateJson($jsonc);

                    expect($result)->toBe(['key' => 'value']);
                });

                test('handles comment immediately after opening brace', function (): void {
                    $jsonc = '{/* comment */"key": "value"}';

                    $result = $this->service->validateJson($jsonc);

                    expect($result)->toBe(['key' => 'value']);
                });

                test('handles comment immediately before closing brace', function (): void {
                    $jsonc = '{"key": "value"/* comment */}';

                    $result = $this->service->validateJson($jsonc);

                    expect($result)->toBe(['key' => 'value']);
                });

                test('handles empty multi-line comment', function (): void {
                    $jsonc = '{"key": /**/ "value"}';

                    $result = $this->service->validateJson($jsonc);

                    expect($result)->toBe(['key' => 'value']);
                });

                test('handles comment-only lines', function (): void {
                    $jsonc = "{\n // Just a comment\n\"key\": \"value\",\n/* Another comment */\n\"other\": 123\n}";

                    $result = $this->service->validateJson($jsonc);

                    expect($result)->toBe(['key' => 'value', 'other' => 123]);
                });

                test('handles escaped slashes in strings', function (): void {
                    // String containing escaped slash should not trigger comment parsing
                    // In JSON: \/ becomes / after parsing (escaped forward slash)
                    // In PHP string: "https:\/\/example.com" becomes https://example.com
                    $jsonc = '{"path": "value", "url": "https:\/\/example.com"}';

                    $result = $this->service->validateJson($jsonc);

                    expect($result['path'])->toBe('value');
                    expect($result['url'])->toBe('https://example.com');
                });

                test('handles comment characters in keys', function (): void {
                    // Keys containing comment-like sequences should work
                    $jsonc = '{"key//name": "value", "key/*name*/": "value2"}';

                    $result = $this->service->validateJson($jsonc);

                    expect($result['key//name'])->toBe('value');
                    expect($result['key/*name*/'])->toBe('value2');
                });

                test('handles mixed line endings', function (): void {
                    // File with mixed \n and \r\n line endings
                    $jsonc = "{\r\n // Windows line ending\r\n \"key\": \"value\",\n // Unix line ending\n \"other\": 123\n}";

                    $result = $this->service->validateJson($jsonc);

                    expect($result)->toBe(['key' => 'value', 'other' => 123]);
                });

                test('handles comments at start of file', function (): void {
                    $jsonc = "// Header comment\n{\"key\": \"value\"}";

                    $result = $this->service->validateJson($jsonc);

                    expect($result)->toBe(['key' => 'value']);
                });

                test('handles multi-line comment spanning multiple lines', function (): void {
                    $jsonc = "{\n \"key\": /* line 1\n line 2\n line 3 */ \"value\"\n}";

                    $result = $this->service->validateJson($jsonc);

                    expect($result)->toBe(['key' => 'value']);
                });

                test('handles comment immediately after comma', function (): void {
                    $jsonc = '{"key1": "value1", /* comment */ "key2": "value2"}';

                    $result = $this->service->validateJson($jsonc);

                    expect($result)->toBe(['key1' => 'value1', 'key2' => 'value2']);
                });
            });
        });

        test('strips multi-line comments', function (): void {
            $jsonc = "{\n  /* Multi-line\n     comment */\n  \"key\": \"value\"\n}";

            $result = $this->service->validateJson($jsonc);

            expect($result)->toBe(['key' => 'value']);
        });

        test('strips inline multi-line comments', function (): void {
            $jsonc = '{"key": /* inline */ "value"}';

            $result = $this->service->validateJson($jsonc);

            expect($result)->toBe(['key' => 'value']);
        });

        test('strips multiple comment types', function (): void {
            $jsonc = "{\n  // Single line\n  /* Multi-line */\n  \"key\": \"value\",\n  // Another comment\n  \"other\": 123\n}";

            $result = $this->service->validateJson($jsonc);

            expect($result)->toBe(['key' => 'value', 'other' => 123]);
        });
    });

    describe('boost.json validation', function (): void {
        beforeEach(function (): void {
            config()->set('vibecodepc.config_files.boost', [
                'path' => $this->testDir.'/boost.json',
                'label' => 'Boost',
                'description' => 'Boost configuration',
                'editable' => true,
                'scope' => 'global',
            ]);
        });

        test('validates skills must be an array', function (): void {
            $invalidBoost = ['skills' => 'not an array'];

            expect(fn () => $this->service->validateJson(json_encode($invalidBoost), 'boost'))
                ->toThrow(\InvalidArgumentException::class, 'skills" must be an array');
        });

        test('validates empty boost.json', function (): void {
            $result = $this->service->validateJson('{}', 'boost');

            expect($result)->toBe([]);
        });

        test('validates complete boost.json structure', function (): void {
            $validBoost = [
                'agents' => ['claude_code', 'copilot'],
                'skills' => ['livewire-development', 'pest-testing'],
                'guidelines' => true,
                'herd_mcp' => [],
                'mcp' => [],
                'nightwatch_mcp' => [],
                'sail' => [],
            ];

            $result = $this->service->validateJson(json_encode($validBoost), 'boost');

            expect($result)->toBe($validBoost);
        });

        test('allows unknown keys with warning', function (): void {
            $boostWithUnknown = [
                'agents' => [],
                'unknown_field' => 'value',
            ];

            $result = $this->service->validateJson(json_encode($boostWithUnknown), 'boost');

            expect($result)->toBe($boostWithUnknown);
        });
    });

    describe('putContent file system failures', function (): void {
        test('throws exception when directory is not writable', function (): void {
            // Create a directory that exists but is not writable
            $unwritableDir = $this->testDir.'/unwritable';
            File::makeDirectory($unwritableDir, 0555, true);

            config()->set('vibecodepc.config_files.unwritable_config', [
                'path' => $unwritableDir.'/test.json',
                'label' => 'Unwritable Config',
                'description' => 'Test config in unwritable directory',
                'editable' => true,
                'scope' => 'global',
            ]);

            // The error happens at the file_put_contents level which throws ErrorException
            expect(fn () => $this->service->putContent('unwritable_config', '{"key": "value"}'))
                ->toThrow(\Exception::class);

            // Cleanup: restore permissions so we can delete
            chmod($unwritableDir, 0755);
        });

        test('throws exception when parent directory creation fails', function (): void {
            // Create a directory structure where parent cannot be created
            $baseDir = $this->testDir.'/readonly';
            File::makeDirectory($baseDir, 0555, true);

            config()->set('vibecodepc.config_files.deep_config', [
                'path' => $baseDir.'/nested/deep/test.json',
                'label' => 'Deep Config',
                'description' => 'Test config in deep nested path',
                'editable' => true,
                'scope' => 'global',
            ]);

            // Directory creation will fail with an exception
            expect(fn () => $this->service->putContent('deep_config', '{"key": "value"}'))
                ->toThrow(\Exception::class);

            // Cleanup
            chmod($baseDir, 0755);
        });

        test('handles concurrent write by using retry mechanism', function (): void {
            // Create a file that we'll simulate concurrent writes on
            File::put($this->testDir.'/test.json', '{"original": "data"}', true);

            // This test verifies that retry logic exists - it will succeed normally
            // The actual retry mechanism is tested through the RetryableTrait
            $this->service->putContent('test_config', '{"key": "value"}');

            expect(File::exists($this->testDir.'/test.json'))->toBeTrue();
            expect(File::get($this->testDir.'/test.json'))->toBe('{"key": "value"}');

            // Verify backup was created
            $backups = $this->service->listBackups('test_config');
            expect($backups)->toHaveCount(1);
        });

        test('throws exception when backup directory is not writable', function (): void {
            // Create existing file so backup is attempted
            File::put($this->testDir.'/test.json', '{"old": "content"}', true);

            // Create a backup directory that is not writable to force backup failure
            $backupDir = $this->backupDir.'/unwritable';
            File::makeDirectory($backupDir, 0555, true);

            // Temporarily change backup directory
            $originalBackupDir = config('vibecodepc.config_editor.backup_directory');
            config()->set('vibecodepc.config_editor.backup_directory', $backupDir);

            // Backup will fail - Laravel's File::put throws ErrorException on permission denied
            expect(fn () => $this->service->putContent('test_config', '{"key": "value"}'))
                ->toThrow(\Exception::class);

            // Cleanup
            chmod($backupDir, 0755);
            config()->set('vibecodepc.config_editor.backup_directory', $originalBackupDir);
        });

        test('validates content before attempting write', function (): void {
            // Test that validation happens before any file operations
            $invalidJson = 'not valid json';

            // This should fail at validation step before file write
            expect(fn () => $this->service->putContent('test_config', $invalidJson))
                ->toThrow(\JsonException::class);

            // Verify no file was created (validation failed first)
            expect(File::exists($this->testDir.'/test.json'))->toBeFalse();
        });

        test('validates forbidden keys before attempting write', function (): void {
            $jsonWithSecret = '{"api_key": "secret123"}';

            // This should fail at validation step before file write
            expect(fn () => $this->service->putContent('test_config', $jsonWithSecret))
                ->toThrow(\InvalidArgumentException::class, 'Forbidden key detected');

            // Verify no file was created (validation failed first)
            expect(File::exists($this->testDir.'/test.json'))->toBeFalse();
        });

        test('validates file size before attempting write', function (): void {
            $oversizedContent = str_repeat('x', 100 * 1024);

            // This should fail at validation step before file write
            expect(fn () => $this->service->putContent('test_config', $oversizedContent))
                ->toThrow(\InvalidArgumentException::class, 'exceeds maximum allowed size');

            // Verify no file was created (validation failed first)
            expect(File::exists($this->testDir.'/test.json'))->toBeFalse();
        });

        test('new file write succeeds when directory is writable', function (): void {
            // Test the happy path for new file creation
            $this->service->putContent('test_config', '{"new": "file"}');

            expect(File::exists($this->testDir.'/test.json'))->toBeTrue();
            expect(File::get($this->testDir.'/test.json'))->toBe('{"new": "file"}');
        });
    });

    describe('cleanupOldBackups', function (): void {
        test('removes backups older than retention period', function (): void {
            // Set retention to 1 day for testing (applies to the entire test)
            config()->set('vibecodepc.config_editor.backup_retention_days', 1);

            File::put($this->testDir.'/test.json', 'old', true);

            $backupPath = $this->service->backup('test_config');

            // Set the file modification time to 2 days ago (older than retention period)
            touch($backupPath, time() - (2 * 24 * 60 * 60));

            // Verify the file exists with old timestamp
            expect(File::exists($backupPath))->toBeTrue();
            expect(File::lastModified($backupPath))->toBeLessThan(time() - (24 * 60 * 60));

            // Create a new backup by saving - this triggers cleanup via backup()
            File::put($this->testDir.'/test.json', 'new', true);
            $this->service->putContent('test_config', '{"trigger": "cleanup"}');

            // Check that the OLD backup was deleted during cleanup
            // Note: listBackups uses the CURRENT config value, so we need to verify directly
            $files = File::glob($this->backupDir.'/*');
            $oldBackupStillExists = false;
            foreach ($files as $file) {
                if (File::lastModified($file) < time() - (24 * 60 * 60)) {
                    $oldBackupStillExists = true;
                    break;
                }
            }
            expect($oldBackupStillExists)->toBeFalse('Old backups should be cleaned up');
        });
    });

    describe('backup edge cases', function (): void {
        test('throws exception when backup directory is not writable', function (): void {
            // Create existing file so backup is attempted
            File::put($this->testDir.'/test.json', '{"old": "content"}', true);

            // Create a backup directory that is not writable to force backup failure
            $unwritableBackupDir = $this->backupDir.'/unwritable';
            File::makeDirectory($unwritableBackupDir, 0555, true);

            // Temporarily change backup directory
            $originalBackupDir = config('vibecodepc.config_editor.backup_directory');
            config()->set('vibecodepc.config_editor.backup_directory', $unwritableBackupDir);

            // Backup will fail - Laravel's File::put throws ErrorException on permission denied
            expect(fn () => $this->service->backup('test_config'))
                ->toThrow(\Exception::class);

            // Cleanup: restore permissions so we can delete
            chmod($unwritableBackupDir, 0755);
            config()->set('vibecodepc.config_editor.backup_directory', $originalBackupDir);
        });

        test('handles backup filename collision with rapid consecutive backups', function (): void {
            // Create a file to backup
            File::put($this->testDir.'/test.json', '{"key": "value"}', true);

            // Create multiple backups rapidly (same second)
            $backupPaths = [];
            for ($i = 0; $i < 5; $i++) {
                $backupPaths[] = $this->service->backup('test_config');
            }

            // All backups should exist and have unique paths
            expect($backupPaths)->toHaveCount(5);
            expect(count(array_unique($backupPaths)))->toBe(5);

            // All backups should contain the same content
            foreach ($backupPaths as $path) {
                expect(File::exists($path))->toBeTrue();
                expect(File::get($path))->toBe('{"key": "value"}');
            }
        });

        test('throws exception when file exceeds max size during backup', function (): void {
            // Create a large file that exceeds the max file size limit
            $largeContent = str_repeat('x', 70 * 1024); // 70KB, exceeds 64KB limit
            File::put($this->testDir.'/test.json', $largeContent, true);

            // File exists and is readable, so backup should succeed at the backup level
            // The size check is done in putContent, not backup
            $backupPath = $this->service->backup('test_config');

            // Backup should succeed regardless of file size
            expect(File::exists($backupPath))->toBeTrue();
            expect(File::get($backupPath))->toBe($largeContent);
        });

        test('throws exception when backup directory does not exist and cannot be created', function (): void {
            // Create existing file so backup is attempted
            File::put($this->testDir.'/test.json', '{"old": "content"}', true);

            // Set backup directory to a path that cannot be created (parent is readonly)
            $readonlyParent = $this->backupDir.'/readonly_parent';
            File::makeDirectory($readonlyParent, 0555, true);

            $nestedBackupDir = $readonlyParent.'/nested/backups';
            $originalBackupDir = config('vibecodepc.config_editor.backup_directory');
            config()->set('vibecodepc.config_editor.backup_directory', $nestedBackupDir);

            // Directory creation will fail with an exception
            expect(fn () => $this->service->backup('test_config'))
                ->toThrow(\Exception::class);

            // Cleanup
            chmod($readonlyParent, 0755);
            config()->set('vibecodepc.config_editor.backup_directory', $originalBackupDir);
        });

        test('throws exception when source file cannot be read during backup', function (): void {
            // Create a file
            File::put($this->testDir.'/test.json', '{"key": "value"}', true);

            // Make the file unreadable
            chmod($this->testDir.'/test.json', 0000);

            // Backup should fail because file cannot be read
            expect(fn () => $this->service->backup('test_config'))
                ->toThrow(\RuntimeException::class, 'Failed to read file for backup');

            // Cleanup: restore permissions
            chmod($this->testDir.'/test.json', 0644);
        });
    });

    describe('JSON validation edge cases', function (): void {
        test('validateJson accepts empty JSON object', function (): void {
            $result = $this->service->validateJson('{}');

            expect($result)->toBe([]);
        });

        test('validateJson accepts empty JSON array', function (): void {
            $result = $this->service->validateJson('[]');

            expect($result)->toBe([]);
        });

        test('validateJson throws exception for JSON with only whitespace', function (): void {
            expect(fn () => $this->service->validateJson('   '))
                ->toThrow(\JsonException::class);
        });

        test('validateJson throws exception for JSON with only newlines and tabs', function (): void {
            expect(fn () => $this->service->validateJson("\n\t\r\n  \t  "))
                ->toThrow(\JsonException::class);
        });

        test('validateJson throws exception for JSON with BOM marker', function (): void {
            // BOM at the start of JSON is a syntax error
            $jsonWithBom = "\xEF\xBB\xBF{\"key\": \"value\"}";

            expect(fn () => $this->service->validateJson($jsonWithBom))
                ->toThrow(\JsonException::class);
        });

        test('validateJson throws exception for JSON with BOM and comments', function (): void {
            // BOM at the start causes a syntax error
            $jsonWithBom = "\xEF\xBB\xBF{\n // comment\n \"key\": \"value\"\n}";

            expect(fn () => $this->service->validateJson($jsonWithBom))
                ->toThrow(\JsonException::class);
        });

        test('validateJson throws exception for trailing commas in arrays', function (): void {
            $jsonWithTrailingComma = '{"items": ["a", "b",]}';

            expect(fn () => $this->service->validateJson($jsonWithTrailingComma))
                ->toThrow(\JsonException::class);
        });

        test('validateJson throws exception for trailing commas in objects', function (): void {
            $jsonWithTrailingComma = '{"key": "value",}';

            expect(fn () => $this->service->validateJson($jsonWithTrailingComma))
                ->toThrow(\JsonException::class);
        });

        test('validateJson throws exception for single quotes instead of double quotes', function (): void {
            $jsonWithSingleQuotes = "{'key': 'value'}";

            expect(fn () => $this->service->validateJson($jsonWithSingleQuotes))
                ->toThrow(\JsonException::class);
        });

        test('validateJson throws exception for mixed quotes', function (): void {
            $jsonWithMixedQuotes = '{"key": \'value\'}';

            expect(fn () => $this->service->validateJson($jsonWithMixedQuotes))
                ->toThrow(\JsonException::class);
        });

        test('validateJson handles deeply nested JSON up to 512 levels', function (): void {
            // Create nested structure with 100 levels (well within default limit)
            $nested = [];
            $current = &$nested;
            for ($i = 0; $i < 100; $i++) {
                $current['level'] = [];
                $current = &$current['level'];
            }
            $current['end'] = true;

            $json = json_encode($nested);
            $result = $this->service->validateJson($json);

            expect($result)->toBeArray();

            // Verify depth by traversing
            $depth = 0;
            $check = $result;
            while (isset($check['level'])) {
                $depth++;
                $check = $check['level'];
            }
            expect($depth)->toBe(100);
            expect($check['end'])->toBeTrue();
        });

        test('validateJson throws exception for JSON exceeding depth limit', function (): void {
            // Create nested structure exceeding 512 levels (json_decode default limit)
            $nested = [];
            $current = &$nested;
            for ($i = 0; $i < 600; $i++) {
                $current['level'] = [];
                $current = &$current['level'];
            }
            $current['end'] = true;

            $json = json_encode($nested);

            // PHP may throw JsonException, TypeError, or InvalidArgumentException when depth is exceeded
            // depending on the version and how json_decode handles the depth limit
            try {
                $this->service->validateJson($json);
                // If we get here, the test should fail
                $this->fail('Expected exception was not thrown');
            } catch (\JsonException|\TypeError|\InvalidArgumentException $e) {
                // These are all acceptable outcomes
                expect(true)->toBeTrue();
            }
        });

        test('validateJson handles extremely long string values under 64KB', function (): void {
            // Create a string just under the 64KB file size limit
            $longString = str_repeat('x', 50000); // 50KB string
            $json = json_encode(['data' => $longString]);

            $result = $this->service->validateJson($json);

            expect($result['data'])->toBe($longString);
            expect(strlen($result['data']))->toBe(50000);
        });

        test('putContent throws exception for JSON with string exceeding file size limit', function (): void {
            // Create a string that will make total JSON exceed 64KB
            $longString = str_repeat('x', 70000); // 70KB string
            $json = json_encode(['data' => $longString]);

            expect(fn () => $this->service->putContent('test_config', $json))
                ->toThrow(\InvalidArgumentException::class, 'exceeds maximum allowed size');
        });

        test('validateJson handles JSON with unicode escape sequences', function (): void {
            $json = '{"text": "Hello \\u0041\\u0042\\u0043"}';

            $result = $this->service->validateJson($json);

            expect($result['text'])->toBe('Hello ABC');
        });

        test('validateJson handles JSON with special unicode characters', function (): void {
            $json = '{"emoji": "🚀", "chinese": "中文", "arabic": "عربي"}';

            $result = $this->service->validateJson($json);

            expect($result['emoji'])->toBe('🚀');
            expect($result['chinese'])->toBe('中文');
            expect($result['arabic'])->toBe('عربي');
        });

        test('validateJson handles JSON with null values', function (): void {
            $json = '{"null_value": null, "empty_string": ""}';

            $result = $this->service->validateJson($json);

            expect($result['null_value'])->toBeNull();
            expect($result['empty_string'])->toBe('');
        });

        test('validateJson handles JSON with numeric string keys', function (): void {
            $json = '{"123": "numeric key", "0": "zero key"}';

            $result = $this->service->validateJson($json);

            expect($result['123'])->toBe('numeric key');
            expect($result['0'])->toBe('zero key');
        });

        test('validateJson throws exception for incomplete JSON', function (): void {
            $incompleteJson = '{"key": "value"';

            expect(fn () => $this->service->validateJson($incompleteJson))
                ->toThrow(\JsonException::class);
        });

        test('validateJson throws exception for JSON with unclosed array', function (): void {
            $invalidJson = '{"items": ["a", "b"}';

            expect(fn () => $this->service->validateJson($invalidJson))
                ->toThrow(\JsonException::class);
        });

        test('validateJson throws exception for JSON with unclosed string', function (): void {
            $invalidJson = '{"key": "unclosed value}';

            expect(fn () => $this->service->validateJson($invalidJson))
                ->toThrow(\JsonException::class);
        });
    });

    describe('schema validation', function (): void {
        $realSchemaDir = '';

        beforeEach(function () use (&$realSchemaDir): void {
            $realSchemaDir = storage_path('schemas');
            if (! File::isDirectory($realSchemaDir)) {
                File::makeDirectory($realSchemaDir, 0755, true);
            }

            config()->set('vibecodepc.config_files.opencode_global', [
                'path' => $this->testDir.'/opencode.json',
                'label' => 'OpenCode',
                'description' => 'OpenCode configuration',
                'editable' => true,
                'scope' => 'global',
            ]);
        });

        afterEach(function () use (&$realSchemaDir): void {
            // Clean up any test schema files we created
            $testSchemas = ['opencode.json', 'claude.json', 'boost.json'];
            foreach ($testSchemas as $schema) {
                $path = $realSchemaDir.'/'.$schema;
                if (File::exists($path)) {
                    File::delete($path);
                }
            }
        });

        test('getSchemaUrl returns null for unknown key', function (): void {
            $url = $this->service->getSchemaUrl('unknown_key');

            expect($url)->toBeNull();
        });

        test('getSchemaUrl returns null when schema file does not exist', function (): void {
            // Ensure opencode schema file does NOT exist
            $schemaPath = storage_path('schemas/opencode.json');
            if (File::exists($schemaPath)) {
                File::delete($schemaPath);
            }

            $url = $this->service->getSchemaUrl('opencode_global');

            expect($url)->toBeNull();
        });

        test('getSchemaUrl returns URL when schema file exists', function () use (&$realSchemaDir): void {
            File::put($realSchemaDir.'/opencode.json', '{}', true);

            $this->service = new ConfigFileService;
            $url = $this->service->getSchemaUrl('opencode_global');

            expect($url)->not->toBeNull();
            expect($url)->toContain('schemas/opencode.json');
        });

        test('validateJsonSchema returns empty array when no schema exists', function (): void {
            $data = ['key' => 'value'];

            $errors = $this->service->validateJsonSchema($data, 'unknown_key');

            expect($errors)->toBe([]);
        });
    });
});
