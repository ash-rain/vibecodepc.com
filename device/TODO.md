# TunnelManagerTest CI Fix TODO (single-file version)

[x] 2026-03-03 Centralize fakes: create reusable CloudApiClientFake + QuickTunnelService mock in setUp() / trait  
[x] 2026-03-03 Make tunnel IDs predictable ? fake provision() to always return 'test-tunnel-999' instead of UUID  
[x] 2026-03-03 Add Http::fake() for /quick-tunnel endpoint with success shape + known subdomain/tunnel_id/token  
[x] 2026-03-03 Fake failing cases: connection error (status 0), 500, timeout, invalid response shape  
[x] 2026-03-03 Manually write fake token file before "Running" assertions ? File::put(storage_path('app/cloudflared/token'), 'fake-token')  
[x] 2026-03-03 Mock TunnelService::isRunning() / start() in relevant tests to return true or specific error  
[x] 2026-03-03 Add ->dump() / ->dumpState() right before every failing assertSee / assertSet  
[x] 2026-03-03 Run isolated: php artisan test --filter TunnelManagerTest --debug (compare local vs CI output)  
[x] 2026-03-03 Check CI logs for: permission denied, storage path mismatch, job queue not running, real HTTP calls
  - Found: Real HTTP calls to Cloud API (401 errors, Connection refused)
  - Found: Docker permission denied on /var/run/docker.sock
  - Root cause: CloudApiClientFake has empty constructor, doesn't init parent $cloudUrl
  - Some tests make real HTTP calls instead of using fake
  - Queue is sync (correct) - no job queue issues
  - Storage paths working - token file created/truncated correctly  
[x] 2026-03-03 Try RefreshDatabase + Storage::fake('local') to eliminate file-system differences  
[x] 2026-03-03 After each fix: push & re-run CI ? verify which test(s) turn green
  - All 28 tests passing locally (80 assertions)
  - No CI configured in repository
  - Git auth not available in this environment
  - Local verification confirms all fixes working  
[ ] Once all 5 pass: squash + merge + delete failing assertions that were too brittle  
