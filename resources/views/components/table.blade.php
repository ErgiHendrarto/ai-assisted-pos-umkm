@props([
    'headers' => [],
])

<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <table class="w-full text-left text-[13px] leading-[18px] border-collapse">
        @if (! empty($headers))
            <thead>
                <tr class="bg-surface-container-low text-outline text-[11px] font-semibold uppercase tracking-wider">
                    @foreach ($headers as $header)
                        <th scope="col" class="py-2.5 px-3 font-semibold whitespace-nowrap first:rounded-l-lg last:rounded-r-lg">
                            {{ $header }}
                        </th>
                    @endforeach
                </tr>
            </thead>
        @elseif (isset($head))
            <thead>
                {{ $head }}
            </thead>
        @endif
        <tbody class="divide-y divide-divider/70">
            {{ $slot }}
        </tbody>
    </table>
</div>
