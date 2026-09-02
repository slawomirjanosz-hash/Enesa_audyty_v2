<section>
    <header>
        <h2 class="text-lg font-medium text-gray-900 dark:text-gray-100">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form method="post" action="{{ route('profile.update') }}" class="mt-6 space-y-6" enctype="multipart/form-data">
        @csrf
        @method('patch')

        <div>
            <x-input-label for="avatar" value="Zdjęcie profilowe" />
            <div class="mt-2 flex items-center gap-4">
                <div style="width:64px;height:64px;border-radius:50%;overflow:hidden;background:#1A4D3A;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:20px;flex-shrink:0;">
                    <x-user-avatar :user="$user" />
                </div>
                <div class="flex-1">
                    <input id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-700 dark:text-gray-300" />
                    <p class="mt-1 text-xs text-gray-500">JPG, PNG lub WEBP, maksymalnie 2 MB.</p>
                    @if($user->avatar_data)
                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300">
                            <input type="checkbox" name="remove_avatar" value="1">
                            Usuń obecne zdjęcie i pokaż inicjały
                        </label>
                    @endif
                </div>
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('avatar')" />
        </div>

        <div>
            <x-input-label for="signature" value="Podpis do dokumentów HR" />
            <div class="mt-2 flex items-center gap-4">
                <div style="width:180px;height:72px;border:1px solid #d1d5db;border-radius:8px;background:#fff;display:flex;align-items:center;justify-content:center;padding:6px;flex-shrink:0;">
                    @if($user->signatureDataUri())
                        <img src="{{ $user->signatureDataUri() }}" alt="Podpis użytkownika" style="display:block;max-width:100%;max-height:100%;object-fit:contain;">
                    @else
                        <span style="font-size:12px;color:#9ca3af">Brak podpisu</span>
                    @endif
                </div>
                <div class="flex-1">
                    <input id="signature" name="signature" type="file" accept="image/jpeg,image/png,image/webp" class="block w-full text-sm text-gray-700 dark:text-gray-300" />
                    <p class="mt-1 text-xs text-gray-500">Najlepiej PNG z przezroczystym lub białym tłem. Maksymalnie 2 MB.</p>
                    @if($user->signature_data)
                        <label class="mt-2 inline-flex items-center gap-2 text-sm text-gray-600 dark:text-gray-300"><input type="checkbox" name="remove_signature" value="1"> Usuń zapisany podpis</label>
                    @endif
                </div>
            </div>
            <x-input-error class="mt-2" :messages="$errors->get('signature')" />
        </div>

        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus autocomplete="name" />
            <x-input-error class="mt-2" :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" required autocomplete="username" />
            <x-input-error class="mt-2" :messages="$errors->get('email')" />

            @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                <div>
                    <p class="text-sm mt-2 text-gray-800 dark:text-gray-200">
                        {{ __('Your email address is unverified.') }}

                        <button form="send-verification" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800">
                            {{ __('Click here to re-send the verification email.') }}
                        </button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 font-medium text-sm text-green-600 dark:text-green-400">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-gray-600 dark:text-gray-400"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
