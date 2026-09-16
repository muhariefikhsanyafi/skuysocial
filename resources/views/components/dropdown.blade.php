@props(['align' => 'right', 'width' => '48', 'contentClasses' => 'py-1'])

@php
$alignmentClasses = match ($align) {
    'left' => 'ltr:origin-top-left rtl:origin-top-right start-0',
    'top' => 'origin-top',
    default => 'ltr:origin-top-right rtl:origin-top-left end-0',
};

$width = match ($width) {
    '48' => 'w-48',
    default => $width,
};
@endphp

<div
    class="relative"
    x-data="{
        open: false,
        closeMenu(restoreFocus) {
            this.open = false;

            if (restoreFocus) {
                this.$nextTick(() => {
                    if (this.$refs.trigger) this.$refs.trigger.focus();
                });
            }
        }
    }"
    @click.outside="closeMenu()"
    @keydown.escape.window="if (open) closeMenu(true)"
    @close.stop="closeMenu()"
>
    <div @click="open = ! open">
        {{ $trigger }}
    </div>

    <div x-show="open"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95"
            x-transition:enter-end="opacity-100 scale-100"
            x-transition:leave="transition ease-in duration-75"
            x-transition:leave-start="opacity-100 scale-100"
            x-transition:leave-end="opacity-0 scale-95"
            class="absolute z-50 mt-2 {{ $width }} {{ $alignmentClasses }}"
            style="display: none;"
            @click="closeMenu()">
        <div class="{{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
