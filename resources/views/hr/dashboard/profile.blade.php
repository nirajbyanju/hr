@extends('layouts.backend')

@section('content')
<div class="wrapper-page">
    <div class="page-title">
        <h1><i class="icon-user"></i> {{ __('My Profile') }}</h1>
    </div>

    @include('partials.flash')

    <div class="page-content">
        <div class="container-fluid">
            <div>
                <div class="content_wrapper content-padded-narrow">
                    <form method="POST" action="{{ route('dashboard.profile.update') }}" enctype="multipart/form-data">
                        @csrf
                        @method('PUT')

                        <div class="row">
                            {{-- Profile photo --}}
                            <div class="col-md-12 form-group mb-4">
                                <label>{{ __('Profile Photo') }}</label>
                                <div class="employee-avatar-card">
                                    <div class="employee-avatar-preview" id="profile_avatar_preview">
                                        @if($user?->hasAvatar())
                                            <img src="{{ $user->avatarUrl() }}" alt="{{ __('Profile Photo') }}">
                                        @else
                                            <i class="icon-user employee-avatar-icon"></i>
                                        @endif
                                    </div>
                                    <div class="employee-avatar-actions">
                                        <input type="file" name="avatar" id="profile_avatar_input" accept=".jpg,.jpeg,.png,.webp">
                                        <label for="profile_avatar_input" class="btn btn-custom btn-sm mb-2">
                                            <i class="icon-picture"></i> {{ __('Upload Photo') }}
                                        </label>
                                        <small id="profile_avatar_file_name" class="text-muted d-block">{{ __('No file chosen') }}</small>
                                        <small class="text-muted d-block mt-1">{{ __('JPG, PNG, WEBP. Max 2MB.') }}</small>
                                        @if($user?->hasAvatar())
                                            <label class="employee-avatar-remove mt-2">
                                                <input type="checkbox" name="remove_avatar" value="1">
                                                <span>{{ __('Remove current photo') }}</span>
                                            </label>
                                        @endif
                                        @error('avatar')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Editable account details --}}
                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Name') }}</label>
                                <input type="text" name="name" class="form-control" value="{{ old('name', $user->name ?? '') }}" required autocomplete="name">
                                @error('name')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-6 form-group mb-3">
                                <label>{{ __('Phone') }}</label>
                                <input type="text" name="phone" class="form-control" value="{{ old('phone', $user->phone ?? '') }}" maxlength="30" autocomplete="tel">
                                @error('phone')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>

                            <div class="col-md-12 form-group mb-2">
                                <label>{{ __('Email') }}</label>
                                <input type="email" class="form-control" value="{{ $user->email ?? '' }}" readonly>
                                <small class="text-muted">{{ __('Email changes are managed by an administrator because your email domain selects the company at login.') }}</small>
                            </div>
                        </div>

                        {{-- Read-only account information --}}
                        <hr class="my-3">
                        <h6 class="text-muted text-uppercase mb-3" style="letter-spacing:.05em;font-size:12px;">{{ __('Account Information') }}</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <div class="text-muted small">{{ __('Role') }}</div>
                                <div class="fw-semibold">
                                    {{ $user?->roles->isNotEmpty() ? $user->roles->pluck('name')->join(', ') : __('No role assigned') }}
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="text-muted small">{{ __('Account Status') }}</div>
                                <div class="fw-semibold">{{ ucwords(str_replace('_', ' ', (string) ($user->account_status ?? '—'))) }}</div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <div class="text-muted small">{{ __('Member Since') }}</div>
                                <div class="fw-semibold">{{ optional($user?->created_at)->format('d M Y') ?? '—' }}</div>
                            </div>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-custom">
                                <i class="icon-check"></i> {{ __('Update Profile') }}
                            </button>
                            <a href="{{ route('dashboard.password.edit') }}" class="btn btn-custom-default">
                                <i class="icon-lock"></i> {{ __('Change Password') }}
                            </a>
                            <a href="{{ route('dashboard') }}" class="btn btn-custom-default"><i class="icon-arrow-left"></i> {{ __('Back') }}</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
    (function () {
        var input = document.getElementById('profile_avatar_input');
        var nameEl = document.getElementById('profile_avatar_file_name');
        var preview = document.getElementById('profile_avatar_preview');
        if (!input || !nameEl || !preview) {
            return;
        }

        input.addEventListener('change', function () {
            if (!input.files || input.files.length === 0) {
                nameEl.textContent = @json(__('No file chosen'));
                return;
            }

            var file = input.files[0];
            nameEl.textContent = file.name;

            var reader = new FileReader();
            reader.onload = function (e) {
                preview.innerHTML = '<img src="' + e.target.result + '" alt="' + @json(__('Profile Photo')) + '">';
            };
            reader.readAsDataURL(file);

            // Picking a new photo cancels an in-progress removal.
            var removeCheckbox = document.querySelector('input[name="remove_avatar"]');
            if (removeCheckbox) {
                removeCheckbox.checked = false;
            }
        });
    })();
</script>
@endpush
