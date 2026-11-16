/**
 * Standardized Rupiah Formatter
 * Handles typing inputs (preserves decimals with comma) and read-only values.
 */
function formatRupiah(angka, prefix) {
    if (!angka && angka !== 0) return '0';
    var number_string = angka.toString().replace(/[^,\d]/g, '');
    if (number_string === '') return '0';
    
    var split = number_string.split(',');
    split[0] = split[0].replace(/^0+(?=\d)/, '');
    if (split[0] === '') split[0] = '0';
    
    var sisa = split[0].length % 3,
        rupiah = split[0].substr(0, sisa),
        ribuan = split[0].substr(sisa).match(/\d{3}/gi);

    if (ribuan) {
        let separator = sisa ? '.' : '';
        rupiah += separator + ribuan.join('.');
    }
    
    let result = split[1] !== undefined ? rupiah + ',' + split[1] : rupiah;
    return prefix ? prefix + result : result;
}

/**
 * Standardized parser for Rupiah inputs.
 * Converts "1.000.000,50" -> 1000000.50 (float)
 */
function parseRupiah(str) {
    if (!str && str !== 0) return 0;
    var number_string = str.toString().replace(/[^,\d]/g, '');
    var split = number_string.split(',');
    var whole = split[0] || '0';
    var decimal = split[1] !== undefined ? '.' + split[1] : '';
    return parseFloat(whole + decimal) || 0;
}

/**
 * Format input element directly
 */
function formatRupiahInput(input) {
    input.value = formatRupiah(input.value);
}
