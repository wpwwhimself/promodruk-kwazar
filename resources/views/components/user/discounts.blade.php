<table id="discounts" style="width: auto;">
    <thead>
        <tr>
            <th>Dostawca</th>
            <th>Rabat prod. (%)</th>
            <th>Rabat znak. (%)</th>
            <th>Rabat usł. dod. (%)</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($suppliers as $supplier)
        <tr>
            <td>{{ $supplier->name }}</td>
            @foreach ($discountTypes as $type)
            <td>
                <x-input-field type="number"
                    :name="$fieldName.'['.$supplier->name.']['.$type.']'"
                    :value="$user?->default_discounts[$supplier->name][$type] ?? 0"
                    :disabled="!in_array($type, $supplier->allowed_discounts ?? [])"
                />
            </td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>

<style>
#discounts input {
    width: 4em;
}
</style>
