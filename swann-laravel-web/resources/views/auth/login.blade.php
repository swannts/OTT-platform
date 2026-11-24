<x-auth-layout>
    @section('title')
        @lang('Login')
    @endsection
    <x-slot name="title">
        @lang('Login')
    </x-slot>

    <x-auth-card>
        <x-slot name="logo">
            @php
                $logo = GetSettingValue('dark_logo')  ? setBaseUrlWithFileName(GetSettingValue('dark_logo'),'image','logos')  : asset('img/logo/dark_logo.png');    
            @endphp

            <a href="{{ route('admin-login') }}">
                <img src="{{ $logo }}" class="img-fluid logo h-4 mb-4">
            </a>
        </x-slot>

        <!-- Session Status -->
        <x-auth-session-status class="mb-4" :status="session('status')" />

        <!-- Social Login -->


        <!-- Validation Errors -->
        <x-auth-validation-errors class="mb-4" :errors="$errors" />

        <form method="POST" action="{{ $url ?? route('admin-login') }}" novalidate id="login-form">
            @csrf

            <!-- Email Address -->
            <div>
                <x-label for="email" :value="__('frontend.email')" />

                <x-input id="email" type="email" name="email" placeholder="{{ __('frontend.enter_email') }}"
                    :value="old('email')" required autofocus />
            </div>
            <div class="invalid-feedback" id="email-error" style="display: none;">{{ __('validation.required', ['attribute' => __('frontend.email')]) }}</div>

            <!-- Password -->
            <div class="mt-4">
                <x-label for="password" :value="__('frontend.password')" />

                <x-input id="password" type="password" name="password"
                    placeholder="{{ __('messages.enter_password') }}" required autocomplete="current-password" />
            </div>
            <div class="invalid-feedback" id="password-error" style="display: none;">{{ __('validation.required', ['attribute' => __('frontend.password')]) }}</div>

            <!-- Remember Me -->
            <div class="d-flex align-items-center justify-content-between mt-4">
                <label for="remember_me" class="d-inline-flex">
                    <input id="remember_me" type="checkbox" class="form-check-input" name="remember">
                    <span class="ms-2">{{ __('frontend.remember_me') }}</span>
                </label>

                @if (Route::has('password.request'))
                    <a class="underline text-sm text-gray-600 hover:text-gray-900"
                        href="{{ route('password.request') }}">
                        {{ __('frontend.forgot_password') }}
                    </a>
                @endif

                
            </div>
            <div class="mt-4">
                <button type="submit" id="submit-btn" class="btn btn-primary w-100">
                    {{ __('frontend.login') }}
                </button>
            </div>

        </form>
        <x-slot name="extra">
            @if (Route::has('register'))
                <p class="text-center text-gray-600 mt-4">
                    Do not have an account? <a href="{{ route('register') }}"
                        class="underline hover:text-gray-900">Register</a>.
                </p>
            @endif
        </x-slot>
    </x-auth-card>

    <script type="text/javascript">
        function disableButton() {
            document.getElementById('submit-btn').classList.add('disabled');
            document.getElementById('submit-btn').innerText = 'Login...';
        }

        function enableButton() {
            document.getElementById('submit-btn').classList.remove('disabled');
            document.getElementById('submit-btn').innerText = '{{ __('frontend.login') }}';
        }

        // Clear validation errors when user starts typing
        document.getElementById('email').addEventListener('input', function() {
            document.getElementById('email-error').style.display = 'none';
            this.classList.remove('is-invalid');
        });

        document.getElementById('password').addEventListener('input', function() {
            document.getElementById('password-error').style.display = 'none';
            this.classList.remove('is-invalid');
        });

        document.getElementById('login-form').addEventListener('submit', function(e) {
            let isValid = true;
            const email = document.getElementById('email');
            const password = document.getElementById('password');
            
            document.getElementById('email-error').style.display = 'none';
            email.classList.remove('is-invalid');
            document.getElementById('password-error').style.display = 'none';
            password.classList.remove('is-invalid');
            
            if (!email.value.trim()) {
                document.getElementById('email-error').style.display = 'block';
                email.classList.add('is-invalid');
                isValid = false;
            }
            
            if (!password.value.trim()) {
                document.getElementById('password-error').style.display = 'block';
                password.classList.add('is-invalid');
                isValid = false;
            }
            
            if (!isValid) {
                e.preventDefault();
                enableButton(); 
            } else {
                disableButton(); 
            }
        });
    </script>
</x-auth-layout>
