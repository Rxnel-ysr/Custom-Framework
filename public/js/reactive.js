class Reactive {
    static components = new Map();

    static init() {
        document.querySelectorAll('[data-reactive]').forEach(el => {
            const component = new ReactiveComponent(el);
            this.components.set(component.id, component);
        });
    }

    static async sendAction(componentId, action, data = {}) {
        const component = this.components.get(componentId);
        if (!component) {
            console.error(`Component ${componentId} not found`);
            return;
        }

        try {
            const response = await fetch('/__reactive', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    __component_name: componentId,
                    __component_action: action,
                    __component_states: component.getStates(),
                    ...data
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const responseData = await response.json();
            component.update(responseData.html, responseData.state);

        } catch (error) {
            console.error('Error sending reactive action:', error);
        }
    }
}

class ReactiveComponentPrev {
    constructor(el) {
        this.el = el;
        this.id = el.dataset.reactiveName;
        this.state = this.parseState(el.dataset.reactiveState);
        this._pendingFocus = null;
        this.bindEvents();
    }

    parseState(stateString) {
        try {
            return stateString ? JSON.parse(stateString) : {};
        } catch (e) {
            console.error('Error parsing state:', e);
            return {};
        }
    }

    getStates() {
        return this.state;
    }

    bindEvents() {
        
        this.el.querySelectorAll('[data-action]').forEach(btn => {
            btn._hasReactiveBinding = btn._hasReactiveBinding || false;
            if (!btn._hasReactiveBinding) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const action = btn.dataset.action;
                    const params = btn.dataset.params ?
                        JSON.parse(btn.dataset.params) : {};
                    Reactive.sendAction(this.id, action, params);
                });
                btn._hasReactiveBinding = true;
            }
        });

        
        this.el.querySelectorAll('[data-onchange]').forEach(input => {
            input._hasReactiveOnChangeBinding = input._hasReactiveOnChangeBinding || false;
            if (!input._hasReactiveOnChangeBinding) {
                const action = input.dataset.onchange;
                const attribute = input.dataset.attribute || 'value';

                const handleChangeEvent = (e) => {
                    const params = this.getInputParams(input, attribute);
                    this.prepareFocusState(input);
                    Reactive.sendAction(this.id, action, params);
                };

                
                const tagName = input.tagName.toLowerCase();
                const type = input.type ? input.type.toLowerCase() : '';

                if (tagName === 'select' || type === 'checkbox' || type === 'radio' || type === 'file') {
                    
                    input.addEventListener('change', handleChangeEvent);
                } else {
                    
                    
                    input.addEventListener('change', handleChangeEvent);
                }

                input._hasReactiveOnChangeBinding = true;
            }
        });

        
        this.el.querySelectorAll('[data-oninput]').forEach(input => {
            input._hasReactiveOnInputBinding = input._hasReactiveOnInputBinding || false;
            if (!input._hasReactiveOnInputBinding) {
                const action = input.dataset.oninput;
                const attribute = input.dataset.attribute || 'value';

                const debounce = (func, delay) => {
                    let timeoutId;
                    return (...args) => {
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => func.apply(this, args), delay);
                    };
                };

                const handleInputEvent = (e) => {
                    const params = this.getInputParams(input, attribute);
                    this.prepareFocusState(input);
                    Reactive.sendAction(this.id, action, params);
                };

                const debouncedHandler = debounce(handleInputEvent, 200);

                const tagName = input.tagName.toLowerCase();
                const type = input.type ? input.type.toLowerCase() : '';

                if (tagName === 'input' || tagName === 'textarea') {
                    if (type === 'checkbox' || type === 'radio') {
                        
                        input.addEventListener('change', debouncedHandler);
                    } else {
                        
                        input.addEventListener('input', debouncedHandler);
                    }
                } else if (tagName === 'select') {
                    
                    input.addEventListener('input', debouncedHandler);
                }

                input._hasReactiveOnInputBinding = true;
            }
        });
    }

    getInputParams(input, attribute) {
        const params = {};
        const tagName = input.tagName.toLowerCase();
        const type = input.type ? input.type.toLowerCase() : '';

        if (type === 'checkbox') {
            params[attribute] = input.checked;
        } else if (type === 'radio') {
            if (input.checked) {
                params[attribute] = input.value;
            }
        } else if (tagName === 'select' && input.multiple) {
            
            params[attribute] = Array.from(input.selectedOptions).map(option => option.value);
        } else if (tagName === 'select' || tagName === 'textarea' ||
            (tagName === 'input' && ['text', 'number', 'email', 'password', 'search', 'tel', 'url'].includes(type))) {
            params[attribute] = input.value;
        } else if (tagName === 'input' && type === 'file') {
            params[attribute] = input.files;
        } else {
            
            params[attribute] = input.value || input.getAttribute(attribute) || input.dataset[attribute];
        }

        return params;
    }

    prepareFocusState(input) {
        this._pendingFocus = {
            id: input.id,
            name: input.name,
            tagName: input.tagName,
            type: input.type,
            value: input.value,
            checked: input.checked,
            selectionStart: input.selectionStart,
            selectionEnd: input.selectionEnd,
            cursorPosition: this.getCursorPosition(input)
        };
    }

    async update(html, state = {}) {
        const activeElement = document.activeElement;
        if (activeElement && this.el.contains(activeElement)) {
            this._pendingFocus = {
                id: activeElement.id,
                name: activeElement.name,
                tagName: activeElement.tagName,
                type: activeElement.type,
                value: activeElement.value,
                checked: activeElement.checked,
                selectionStart: activeElement.selectionStart,
                selectionEnd: activeElement.selectionEnd,
                componentId: this.id
            };
        }

        if (state) {
            this.state = state;
            this.el.dataset.reactiveState = JSON.stringify(state);
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        const newInner = doc.body.firstElementChild;

        if (newInner) {
            this.el.innerHTML = newInner.innerHTML;
        } else {
            this.el.innerHTML = html;
        }

        const scrollTop = this.el.scrollTop;
        this.el.scrollTop = scrollTop;

        this.bindEvents();

        if (this._pendingFocus) {
            await new Promise(resolve => setTimeout(resolve, 50));
            this.restoreFocus();
        }
    }

    getCursorPosition(element) {
        if (!element) return null;

        if (element.isContentEditable) {
            const selection = window.getSelection();
            if (selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                return {
                    type: 'contenteditable',
                    range: range.cloneRange()
                };
            }
            return null;
        }

        return {
            type: 'input',
            start: element.selectionStart,
            end: element.selectionEnd,
            direction: element.selectionDirection
        };
    }

    restoreFocus() {
        if (!this._pendingFocus) return;

        if (this._pendingFocus.componentId && this._pendingFocus.componentId !== this.id) {
            console.debug('Focus belongs to different component, skipping');
            this._pendingFocus = null;
            return;
        }

        const { id, name, tagName, type, value, checked, selectionStart, selectionEnd } = this._pendingFocus;
        let elementToFocus = null;

        const searchRoot = this.el;

        if (id) {
            elementToFocus = searchRoot.querySelector(`#${id}`);
        }

        if (!elementToFocus && name) {
            const candidates = searchRoot.querySelectorAll(`${tagName}[name="${name}"]`);
            if (candidates.length > 0) {
                if (type === 'radio') {
                    elementToFocus = Array.from(candidates).find(el => el.value === value) || candidates[0];
                } else {
                    elementToFocus = candidates[0];
                }
            }
        }

        if (!elementToFocus && tagName) {
            const candidates = searchRoot.querySelectorAll(tagName);
            if (candidates.length > 0) {
                elementToFocus = candidates[0];
            }
        }

        if (elementToFocus) {
            if (this.el.contains(elementToFocus)) {
                elementToFocus.focus();

                
                if (value !== undefined && elementToFocus.value !== value) {
                    elementToFocus.value = value;
                }

                if (checked !== undefined && elementToFocus.checked !== checked) {
                    elementToFocus.checked = checked;
                }

                
                if (typeof selectionStart === 'number' && typeof selectionEnd === 'number' &&
                    elementToFocus.setSelectionRange) {
                    try {
                        elementToFocus.setSelectionRange(selectionStart, selectionEnd);
                    } catch (e) {
                        console.debug('Could not restore selection range', e);
                    }
                }
            } else {
                console.warn('Attempted to focus element outside component', elementToFocus);
            }
        }

        this._pendingFocus = null;
    }
}

class ReactiveComponent {
    constructor(el) {
        this.el = el;
        this.id = el.dataset.reactiveName;
        this.state = this.parseState(el.dataset.reactiveState);
        this._pendingFocus = null;
        this.bindEvents();
    }

    
    static INPUT_TYPES = {
        TEXT: ['text', 'email', 'password', 'search', 'tel', 'url'],
        NUMBER: ['number', 'range'],
        BOOLEAN: ['checkbox', 'radio'],
        FILE: ['file'],
        SELECT: ['select-one', 'select-multiple'],
        DATE: ['date', 'time', 'datetime-local', 'month', 'week'],
        COLOR: ['color'],
        HIDDEN: ['hidden'],
        BUTTON: ['button', 'submit', 'reset', 'image']
    };

    getStates() {
        return this.state;
    }

    parseState(stateString) {
        try {
            return stateString ? JSON.parse(stateString) : {};
        } catch (e) {
            console.error('Error parsing state:', e);
            return {};
        }
    }

    bindEvents() {

        this.el.querySelectorAll('[data-action]').forEach(btn => {
            btn._hasReactiveBinding = btn._hasReactiveBinding || false;
            if (!btn._hasReactiveBinding) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const action = btn.dataset.action;
                    const params = btn.dataset.params ?
                        JSON.parse(btn.dataset.params) : {};
                    Reactive.sendAction(this.id, action, params);
                });
                btn._hasReactiveBinding = true;
            }
        });

        
        this.el.querySelectorAll('input, select, textarea').forEach(input => {
            this.bindInputEvents(input);
        });

        
        this.el.querySelectorAll('[contenteditable="true"]').forEach(el => {
            this.bindContentEditableEvents(el);
        });
    }

    bindInputEvents(input) {
        const tagName = input.tagName.toLowerCase();
        const type = input.type ? input.type.toLowerCase() : tagName;

        
        if (input._reactiveBound) return;
        input._reactiveBound = true;

        
        const onchangeAction = input.dataset.onchange;
        const oninputAction = input.dataset.oninput;
        const debouchDelay = input.dataset.delay || 200;
        const attribute = input.dataset.attribute || 'value';

        
        const debounce = (func, delay) => {
            let timeoutId;
            return (...args) => {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => func.apply(this, args), delay);
            };
        };

        
        if (onchangeAction) {
            const handleChange = (e) => {
                const params = this.getInputParams(input, attribute);
                this.prepareFocusState(input);
                Reactive.sendAction(this.id, onchangeAction, params);
            };

            
            if (ReactiveComponent.INPUT_TYPES.BOOLEAN.includes(type)) {
                input.addEventListener('change', handleChange);
            }
            else if (ReactiveComponent.INPUT_TYPES.SELECT.includes(type) ||
                tagName === 'select') {
                input.addEventListener('change', handleChange);
            }
            else if (ReactiveComponent.INPUT_TYPES.FILE.includes(type)) {
                input.addEventListener('change', handleChange);
            }
            else {
                
                input.addEventListener('change', handleChange);
            }
        }

        
        if (oninputAction) {
            const debouncedInput = debounce((e) => {
                const params = this.getInputParams(input, attribute);
                this.prepareFocusState(input);
                Reactive.sendAction(this.id, oninputAction, params);
            }, debouchDelay);

            if (ReactiveComponent.INPUT_TYPES.TEXT.includes(type) ||
                ReactiveComponent.INPUT_TYPES.NUMBER.includes(type) ||
                tagName === 'textarea') {
                input.addEventListener('input', debouncedInput);
            }
            else if (tagName === 'select') {
                input.addEventListener('input', debouncedInput);
            }
        }
    }

    bindContentEditableEvents(el) {
        if (el._reactiveBound) return;
        el._reactiveBound = true;

        const onchangeAction = el.dataset.onchange;
        const oninputAction = el.dataset.oninput;
        const debouchDelay = input.dataset?.delay || 200;
        const attribute = el.dataset.attribute || 'textContent';

        const debounce = (func, delay) => {
            let timeoutId;
            return (...args) => {
                clearTimeout(timeoutId);
                timeoutId = setTimeout(() => func.apply(this, args), delay);
            };
        };

        if (onchangeAction) {
            const handleChange = (e) => {
                const params = { [attribute]: el.innerHTML };
                this.prepareFocusState(el);
                Reactive.sendAction(this.id, onchangeAction, params);
            };
            el.addEventListener('blur', handleChange);
        }

        if (oninputAction) {
            const debouncedInput = debounce((e) => {
                const params = { [attribute]: el.innerHTML };
                this.prepareFocusState(el);
                Reactive.sendAction(this.id, oninputAction, params);
            }, debouchDelay);
            el.addEventListener('input', debouncedInput);
        }
    }

    getInputParams(input, attribute) {
        const tagName = input.tagName.toLowerCase();
        const type = input.type ? input.type.toLowerCase() : tagName;
        const params = {};

        
        if (ReactiveComponent.INPUT_TYPES.BOOLEAN.includes(type)) {
            if (type === 'checkbox') {
                params[attribute] = input.checked;
            } else if (type === 'radio' && input.checked) {
                params[attribute] = input.value;
            }
        }
        else if (ReactiveComponent.INPUT_TYPES.FILE.includes(type)) {
            params[attribute] = input.files;
        }
        else if (tagName === 'select' && input.multiple) {
            params[attribute] = Array.from(input.selectedOptions).map(opt => opt.value);
        }
        else if (tagName === 'select') {
            params[attribute] = input.value;
        }
        else if (ReactiveComponent.INPUT_TYPES.DATE.includes(type)) {
            params[attribute] = input.valueAsDate || input.value;
        }
        else if (ReactiveComponent.INPUT_TYPES.NUMBER.includes(type)) {
            params[attribute] = type === 'range' ? parseFloat(input.value) :
                (input.valueAsNumber || input.value);
        }
        else if (ReactiveComponent.INPUT_TYPES.COLOR.includes(type)) {
            params[attribute] = input.value;
        }
        else {
            
            params[attribute] = input.value;
        }

        return params;
    }

    prepareFocusState(element) {
        this._pendingFocus = {
            id: element.id,
            name: element.name,
            tagName: element.tagName,
            type: element.type,
            value: element.value,
            checked: element.checked,
            selectionStart: element.selectionStart,
            selectionEnd: element.selectionEnd,
            cursorPosition: this.getCursorPosition(element)
        };
    }

    getCursorPosition(element) {
        if (!element) return null;

        if (element.isContentEditable) {
            const selection = window.getSelection();
            if (selection.rangeCount > 0) {
                const range = selection.getRangeAt(0);
                return {
                    type: 'contenteditable',
                    range: range.cloneRange()
                };
            }
            return null;
        }

        return {
            type: 'input',
            start: element.selectionStart,
            end: element.selectionEnd,
            direction: element.selectionDirection
        };
    }

    async update(html, state = {}) {
        const activeElement = document.activeElement;
        if (activeElement && this.el.contains(activeElement)) {
            this._pendingFocus = {
                id: activeElement.id,
                name: activeElement.name,
                tagName: activeElement.tagName,
                type: activeElement.type,
                value: activeElement.value,
                checked: activeElement.checked,
                selectionStart: activeElement.selectionStart,
                selectionEnd: activeElement.selectionEnd,
                componentId: this.id
            };
        }

        if (state) {
            this.state = state;
            this.el.dataset.reactiveState = JSON.stringify(state);
        }

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        const newInner = doc.body.firstElementChild;

        if (newInner) {
            this.el.innerHTML = newInner.innerHTML;
        } else {
            this.el.innerHTML = html;
        }

        const scrollTop = this.el.scrollTop;
        this.el.scrollTop = scrollTop;

        this.bindEvents();

        if (this._pendingFocus) {
            await new Promise(resolve => setTimeout(resolve, 50));
            this.restoreFocus();
        }
    }

    restoreFocus() {
        if (!this._pendingFocus) return;

        if (this._pendingFocus.componentId && this._pendingFocus.componentId !== this.id) {
            console.debug('Focus belongs to different component, skipping');
            this._pendingFocus = null;
            return;
        }

        const { id, name, tagName, type, value, checked, selectionStart, selectionEnd } = this._pendingFocus;
        let elementToFocus = null;

        const searchRoot = this.el;

        if (id) {
            elementToFocus = searchRoot.querySelector(`#${id}`);
        }

        if (!elementToFocus && name) {
            const candidates = searchRoot.querySelectorAll(`${tagName}[name="${name}"]`);
            if (candidates.length > 0) {
                if (type === 'radio') {
                    elementToFocus = Array.from(candidates).find(el => el.value === value) || candidates[0];
                } else {
                    elementToFocus = candidates[0];
                }
            }
        }

        if (!elementToFocus && tagName) {
            const candidates = searchRoot.querySelectorAll(tagName);
            if (candidates.length > 0) {
                elementToFocus = candidates[0];
            }
        }

        if (elementToFocus) {
            if (this.el.contains(elementToFocus)) {
                elementToFocus.focus();


                if (value !== undefined && elementToFocus.value !== value) {
                    elementToFocus.value = value;
                }

                if (checked !== undefined && elementToFocus.checked !== checked) {
                    elementToFocus.checked = checked;
                }


                if (typeof selectionStart === 'number' && typeof selectionEnd === 'number' &&
                    elementToFocus.setSelectionRange) {
                    try {
                        elementToFocus.setSelectionRange(selectionStart, selectionEnd);
                    } catch (e) {
                        console.debug('Could not restore selection range', e);
                    }
                }
            } else {
                console.warn('Attempted to focus element outside component', elementToFocus);
            }
        }

        this._pendingFocus = null;
    }
    

}

/*

Reactive.components.set('input-component', ReactiveComponent);
*/