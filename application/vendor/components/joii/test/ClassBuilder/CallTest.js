/*
Javascript Object                               ______  ________________
Inheritance Implementation                  __ / / __ \/  _/  _/\_____  \
                                           / // / /_/ // /_/ /    _(__  <
Copyright 2014, Harold Iedema.             \___/\____/___/___/   /       \
--------------------------------------------------------------- /______  / ---
Permission is hereby granted, free of charge, to any person obtaining  \/
a copy of this software and associated documentation files (the
"Software"), to deal in the Software without restriction, including
without limitation the rights to use, copy, modify, merge, publish,
distribute, sublicense, and/or sell copies of the Software, and to
permit persons to whom the Software is furnished to do so, subject to
the following conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF
MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE
LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION
OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION
WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
------------------------------------------------------------------------------
*/

/**
* Tests the functionality of __call.
*/
test('ClassBuilder:CallTest', function(assert) {

    var C1 = JOII.ClassBuilder({
        __call: function() { return true; }
    });
    assert.strictEqual(C1(), true, '__call function OK.');

    // __call may not return "this", because it references to the static
    // definition of the class body.
    assert.throws(function() {
        var a = JOII.ClassBuilder({ 'public function foo' : function() {}, __call: function() { return this; }}); a();
    }, function(err) { return err === '__call cannot return itself.'; }, '__call cannot return itself.');

    // Test the context of a static call.
    var C2 = JOII.ClassBuilder({
        a: 1,

        __call: function(val) {
            if (!val) {
                return this.a;
            }
            this.a = val;
        }
    });

    var c2 = new C2();
    assert.strictEqual(c2.getA(), 1, 'c2.getA() returns this.a (1)');
    assert.strictEqual(C2(), 1, '__call returns this.a (1)');

    // Update the value, THEN create another instance and check again...
    C2(2);
    var c2a = new C2();
    assert.strictEqual(C2(), 2, '__call returns this.a (2)');
    assert.strictEqual(c2.getA(), 1, 'c2.getA() returns this.a (2)');
    assert.strictEqual(c2a.getA(), 1, 'c2a.getA() returns this.a (2)');


    // 3.1.0: Custom callable method names.

    var C3 = Class({'<>': function() { return 1; } });
    assert.strictEqual(C3(), 1, 'New default call method "<>" used.');

    JOII.Config.addCallable('execute');
    var C4 = Class({'execute': function() { return 2; } });
    assert.strictEqual(C4(), 2, 'Custom call method "execute" used.');

});
