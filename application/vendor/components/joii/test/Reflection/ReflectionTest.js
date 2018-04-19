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
 * Test the behavior of the Reflection mechanism which retrieves metadata from
 * a defined JOII-class, its properties and methods.
 */
test('Reflection:ReflectionTest', function(assert) {

    var BaseClass = JOII.ClassBuilder({}, {
        'final protected nullable number a_number' : 1
    });

    var ChildClass = JOII.ClassBuilder({ 'extends' : BaseClass }, {
        'protected nullable string a_string' : 'Hello World'
    });

    var EndClass = JOII.ClassBuilder({ 'extends' : ChildClass }, {
        'public setNumber' : function(i) {
            this.a_number = i + 10;
        }
    });

    var base_reflector  = new JOII.Reflection.Class(BaseClass),
        child_reflector = new JOII.Reflection.Class(ChildClass),
        end_reflector   = new JOII.Reflection.Class(EndClass);

    assert.equal(base_reflector.getProperties().length, 1, 'Amount of properties in BaseClass is 1.');
    assert.equal(child_reflector.getProperties().length, 2, 'Amount of properties in ChildClass is 2.');
    assert.equal(end_reflector.getProperties().length, 2, 'Amount of properties in EndClass is 2.');
    assert.equal(base_reflector.getMethods().length, 2, 'Amount of methods in BaseClass is 3.');
    assert.equal(child_reflector.getMethods().length, 4, 'Amount of methods in ChildClass is 5.');
    assert.equal(end_reflector.getMethods().length, 5, 'Amount of methods in EndClass is 6.');

    assert.equal(base_reflector.hasParent(), false, 'BaseClass does not have a parent.');
    assert.equal(child_reflector.hasParent(), true, 'ChildClass has a parent.');
    assert.equal(end_reflector.hasParent(), true, 'EndClass has have a parent.');

    assert.equal(base_reflector.getProperty('a_number').getName(), 'a_number', 'a_number reflects name OK from BaseClass.');
    assert.equal(child_reflector.getProperty('a_string').getName(), 'a_string', 'a_string reflects name OK from ChildClass.');
    assert.equal(end_reflector.getProperty('a_number').getName(), 'a_number', 'a_number reflects name OK from EndClass.');

    var prop_a = base_reflector.getProperty('a_number'),
        prop_b = child_reflector.getProperty('a_string'),
        method = end_reflector.getMethod('setNumber');

    assert.equal(prop_a.isType('number'), true, 'prop_a is a number.');
    assert.equal(prop_a.isAbstract(), false, 'prop_a is not abstract.');
    assert.equal(prop_a.isFinal(), true, 'prop_a is final.');
    assert.equal(prop_a.isProtected(), true, 'prop_a is protected.');
    assert.equal(prop_a.isPublic(), false, 'prop_a is not public.');
    assert.equal(prop_a.isNullable(), true, 'prop_a is nullable.');

    assert.equal(prop_b.isType('string'), true, 'prop_b is a string.');
    assert.equal(prop_b.isAbstract(), false, 'prop_b is not abstract.');
    assert.equal(prop_b.isFinal(), false, 'prop_b is not final.');
    assert.equal(prop_b.isProtected(), true, 'prop_b is protected.');
    assert.equal(prop_b.isPublic(), false, 'prop_b is not public.');
    assert.equal(prop_b.isNullable(), true, 'prop_b is nullable.');

    assert.equal(method.isType('function'), true, 'method is a function.');
    assert.equal(method.isAbstract(), false, 'method is not abstract.');
    assert.equal(method.isFinal(), false, 'method is not final.');
    assert.equal(method.isProtected(), false, 'method is not protected.');
    assert.equal(method.isPublic(), true, 'method is public.');
    assert.equal(method.isNullable(), false, 'method is not nullable.');

    // Test parent reflectors.
    var parent_base = child_reflector.getParent(),
        child_base  = end_reflector.getParent();

    assert.equal(parent_base.getProperties().length, 1, 'Amount of properties in BaseClass (via Child.parent) is 1.');
    assert.equal(child_base.getProperties().length, 2, 'Amount of properties in ChildClass (via End.parent) is 2.');
    assert.equal(parent_base.getMethods().length, 2, 'Amount of methods in BaseClass (via Child.parent) is 2.');
    assert.equal(child_base.getMethods().length, 4, 'Amount of methods in ChildClass (via End.parent) is 4.');

});
