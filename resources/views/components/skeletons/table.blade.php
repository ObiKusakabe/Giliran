@props(['columns' => 4, 'rows' => 5])

{{-- Table Skeleton --}}
<flux:skeleton.group animate="shimmer">
    <flux:table>
        <flux:table.columns>
            @for ($i = 0; $i < $columns; $i++)
                <flux:table.column>
                    <flux:skeleton.line style="width: {{ rand(60, 100) }}%" />
                </flux:table.column>
            @endfor
        </flux:table.columns>
        <flux:table.rows>
            @for ($r = 0; $r < $rows; $r++)
                <flux:table.row>
                    @for ($c = 0; $c < $columns; $c++)
                        <flux:table.cell>
                            <flux:skeleton.line style="width: {{ rand(50, 100) }}%" />
                        </flux:table.cell>
                    @endfor
                </flux:table.row>
            @endfor
        </flux:table.rows>
    </flux:table>
</flux:skeleton.group>
