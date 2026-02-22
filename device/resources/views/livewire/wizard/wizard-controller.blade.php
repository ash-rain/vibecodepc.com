<div class="min-h-screen flex flex-col px-4 py-8">
    {{-- Header --}}
    <div class="text-center mb-6">
        <h1 class="text-2xl font-bold text-emerald-400">VibeCodePC Setup</h1>
        <p class="text-gray-400 text-sm mt-1">Configure your personal coding workstation</p>
    </div>

    {{-- Progress Bar --}}
    <x-wizard.progress-bar :steps="$steps" :current-step="$currentStep" />

    {{-- Step Content --}}
    <div class="flex-1 flex items-start justify-center mt-8">
        <div class="w-full max-w-2xl">
            @livewire('wizard.' . str_replace('_', '-', $currentStep), [], key($currentStep))
        </div>
    </div>
</div>
