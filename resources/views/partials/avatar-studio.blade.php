@php
    $avatarStyles = \App\Services\GravatarAvatar::STYLES;
    $currentAvatarStyle = preg_match('~^avatars/gravatar/(wavatar|robohash|retro|monsterid|identicon)-~', (string) $user->avatar, $styleMatch)
        ? $styleMatch[1] : null;
    $studioConfig = [
        'saveUrl' => route('avatar.store'),
        'previews' => collect($avatarStyles)->mapWithKeys(fn ($style) => [$style => app(\App\Services\GravatarAvatar::class)->previewUrl($user, $style)])->all(),
        'initialUrl' => $user->avatar ? asset('storage/'.$user->avatar) : '',
        'currentStyle' => $currentAvatarStyle,
        'failedMessage' => __('avatar.failed'),
        'userId' => (int) $user->getKey(),
    ];
@endphp

<div x-data="avatarStudio(@js($studioConfig))"
     @open-avatar-studio.window="openStudio(); $nextTick(() => $refs.studioClose.focus())"
     @keydown.escape.window="if (visible) closeStudio()"
     x-show="visible" style="display: none;" class="skuy-avatar-studio-scrim"
     @click.self="closeStudio()">
    <section role="dialog" aria-modal="true" aria-labelledby="avatar-studio-title" aria-describedby="avatar-studio-description"
             class="skuy-avatar-studio" @keydown.tab="trapFocus($event, $el)" :aria-busy="saving.toString()">
        <header class="skuy-avatar-studio__header">
            <div>
                <h2 id="avatar-studio-title">{{ __('avatar.title') }}</h2>
                <p id="avatar-studio-description">{{ __('avatar.description') }}</p>
            </div>
            <button type="button" x-ref="studioClose" data-avatar-studio-close @click="closeStudio()" :disabled="saving" aria-label="{{ __('avatar.close') }}" class="skuy-avatar-studio__close">
                <x-fa-icon name="xmark" weight="solid" />
            </button>
        </header>

        <div class="skuy-avatar-studio__modes" role="group" aria-label="{{ __('avatar.title') }}">
            <button type="button" :aria-pressed="(mode === 'upload').toString()" :disabled="saving" @click="mode = 'upload'; error = ''; success = ''">
                <x-fa-icon name="image" /> {{ __('avatar.upload') }}
            </button>
            <button type="button" :aria-pressed="(mode === 'gravatar').toString()" :disabled="saving" @click="mode = 'gravatar'; error = ''; success = ''">
                <x-fa-icon name="face-smile" /> {{ __('avatar.gravatar') }}
            </button>
        </div>

        <div class="skuy-avatar-studio__body">
            <div class="skuy-avatar-studio__preview">
                <div class="skuy-avatar-studio__portrait" role="img" aria-label="{{ __('avatar.preview') }}">
                    <span aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
                    <template x-if="visible && preview">
                        <img :src="preview" alt="" decoding="async" @load="$el.style.visibility = 'visible'" x-on:error="$el.style.visibility = 'hidden'">
                    </template>
                </div>
                <p>{{ __('avatar.preview') }}</p>
                <span x-show="mode === 'gravatar'" x-text="selected === 'monsterid' ? 'MonsterID' : selected === 'robohash' ? 'Robohash' : selected.charAt(0).toUpperCase() + selected.slice(1)"></span>
            </div>

            <div class="skuy-avatar-studio__choices">
                <div x-show="mode === 'upload'" class="skuy-avatar-studio__upload">
                    <x-fa-icon name="image" class="skuy-avatar-studio__upload-icon" />
                    <label for="avatar-studio-file">{{ __('avatar.choose_file') }}</label>
                    <input id="avatar-studio-file" data-avatar-studio-upload type="file" accept="image/jpeg,image/png,image/gif" @change="selectFile($event)" :disabled="saving">
                    <p>{{ __('avatar.upload_hint') }}</p>
                </div>
                <template x-if="visible && mode === 'gravatar'">
                    <div class="skuy-avatar-studio__styles" role="group" aria-label="{{ __('avatar.styles') }}">
                        @foreach ($avatarStyles as $style)
                            <button type="button" class="skuy-avatar-studio__style" @click="chooseStyle('{{ $style }}')" :disabled="saving" :aria-pressed="(selected === '{{ $style }}').toString()" aria-label="{{ $style === 'monsterid' ? 'MonsterID' : ucfirst($style) }}">
                                <span class="skuy-avatar-studio__sample">
                                    <span x-show="status['{{ $style }}'] !== 'ready'" aria-hidden="true">{{ mb_substr($user->name, 0, 1) }}</span>
                                    <img :src="styleUrl('{{ $style }}')" alt="" loading="lazy" decoding="async"
                                         @load="imageLoaded('{{ $style }}', $el)" x-on:error="status['{{ $style }}'] = 'failed'"
                                         :class="{ 'is-failed': status['{{ $style }}'] === 'failed' }">
                                </span>
                                <strong>{{ $style === 'monsterid' ? 'MonsterID' : ucfirst($style) }}</strong>
                                <span class="skuy-avatar-studio__selection" x-show="selected === '{{ $style }}'"><x-fa-icon name="check" weight="solid" /> {{ __('avatar.selected') }}</span>
                                <span class="skuy-avatar-studio__current" x-show="currentStyle === '{{ $style }}'">{{ __('avatar.current') }}</span>
                            </button>
                        @endforeach
                    </div>
                </template>
            </div>
        </div>

        <div class="skuy-avatar-studio__feedback" aria-live="polite">
            <p x-show="mode === 'gravatar' && !status[selected]" role="status">{{ __('avatar.loading') }}</p>
            <div x-show="mode === 'gravatar' && status[selected] === 'failed'">
                <p>{{ __('avatar.failed') }}</p>
                <button type="button" @click="retry()" :disabled="saving">{{ __('avatar.retry') }}</button>
            </div>
            <p x-show="error" x-text="error" role="alert" class="skuy-avatar-studio__error"></p>
            <p x-show="success" x-text="success" role="status" class="skuy-avatar-studio__success"></p>
        </div>
        <p class="skuy-avatar-studio__privacy">{{ __('avatar.privacy') }}</p>
        <footer class="skuy-avatar-studio__footer">
            <button type="button" @click="closeStudio()" :disabled="saving">{{ __('avatar.cancel') }}</button>
            <button type="button" @click="save()" :disabled="!canSave" class="skuy-avatar-studio__save">
                <span x-show="!saving">{{ __('avatar.save') }}</span><span x-show="saving">{{ __('avatar.saving') }}</span>
            </button>
        </footer>
    </section>
</div>
