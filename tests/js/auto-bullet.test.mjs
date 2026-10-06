import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function enter(value, { start = value.length, end = start, maxLength = 3000, ...modifiers } = {}) {
    const handlers = {};
    let saved = false;
    let prevented = false;
    const field = {
        value, selectionStart: start, selectionEnd: end, maxLength,
        addEventListener: (type, callback) => { handlers[type] = callback; },
        setRangeText(text, from, to) {
            this.value = this.value.slice(0, from) + text + this.value.slice(to);
            this.selectionStart = this.selectionEnd = from + text.length;
        },
        dispatchEvent: () => { saved = true; },
    };
    const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
    vm.runInNewContext(source.slice(source.indexOf('// Bullet tetap')), {
        document: { querySelectorAll: () => [field] }, Event,
    });
    handlers.keydown({ key: 'Enter', preventDefault: () => { prevented = true; }, ...modifiers });
    return { value: field.value, caret: field.selectionStart, saved, prevented };
}

assert.equal(enter('Hasil pertama').value, '• Hasil pertama\n• ');
assert.equal(enter('• Hasil pertama').value, '• Hasil pertama\n• ');
assert.equal(enter('• Hasil pertama\n• ').value, '• Hasil pertama\n');
assert.equal(enter('- Hasil').value, '- Hasil\n- ');
assert.equal(enter('• Hasil berikut', { start: 7 }).value, '• Hasil\n•  berikut');
assert.equal(enter('Hasil salah', { start: 5, end: 11 }).value, '• Hasil\n• ');
assert.equal(enter('Hasil', { shiftKey: true }).prevented, false);
assert.equal(enter('Hasil', { isComposing: true }).prevented, false);
assert.equal(enter('Hasil', { maxLength: 5 }).value, 'Hasil');
assert.equal(enter('Hasil').saved, true);
const source = readFileSync(new URL('../../resources/js/app.js', import.meta.url), 'utf8');
const listeners = {};
const field = {
    value: '', maxLength: 3000, selectionStart: 0, selectionEnd: 0,
    addEventListener: (type, callback) => { listeners[type] = callback; },
    setRangeText(text, start, end) { this.value = this.value.slice(0, start) + text + this.value.slice(end); },
    setSelectionRange(start, end) { this.selectionStart = start; this.selectionEnd = end; },
    dispatchEvent(event) { listeners[event.type](event); },
};
vm.runInNewContext(source.slice(source.indexOf('// Bullet tetap')), {
    document: { querySelectorAll: () => [field] }, Event,
});
field.value = 'H';
field.selectionStart = field.selectionEnd = 1;
listeners.input({});
assert.equal(field.value, '• H');
assert.equal(field.selectionStart, 3);
listeners.input({});
assert.equal(field.value, '• H');
field.value = '';
listeners.input({});
field.value = 'Hasil ditempel';
listeners.input({});
assert.equal(field.value, '• Hasil ditempel');
field.value = '';
listeners.input({});
field.value = '文';
listeners.input({ isComposing: true });
assert.equal(field.value, '文');
listeners.compositionend();
assert.equal(field.value, '• 文');
console.log('Auto bullet: 16 checks passed.');
