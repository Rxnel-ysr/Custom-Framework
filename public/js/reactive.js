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

class ReactiveComponent {
    constructor(el) {
        this.el = el;
        this.id = el.dataset.reactiveName;
        this.state = this.parseState(el.dataset.reactiveState);
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
            input._hasReactiveBinding = input._hasReactiveBinding || false;
            if (!input._hasReactiveBinding) {
                const inputTypes = {
                    value: ['text', 'number', 'email', 'password', 'search', 'tel', 'url', 'textarea', 'select-one'],
                    checked: ['checkbox', 'radio'],
                };

                const action = input.dataset.onchange;
                const attribute = input.dataset.attribute || 'value';

                const debounce = (func, delay) => {
                    let timeoutId;
                    return (...args) => {
                        clearTimeout(timeoutId);
                        timeoutId = setTimeout(() => func.apply(this, args), delay);
                    };
                };

                const handleInputEvent = (e) => {
                    const params = {};
                    const type = input.type.toLowerCase();
                    const tagName = input.tagName.toLowerCase();

                    if (inputTypes.checked.includes(type)) {
                        params[attribute] = input.checked;
                        if (type === 'radio' && input.checked) {
                            params[attribute] = input.value;
                        }
                    }
                    else if (tagName === 'select' || inputTypes.value.includes(type)) {
                        params[attribute] = input.value;
                    }
                    else {
                        params[attribute] = input.dataset[attribute] || input.getAttribute(attribute);
                    }

                    this._pendingFocus = {
                        id: input.id,
                        name: input.name,
                        tagName: input.tagName,
                        type: input.type,
                        value: input.value,
                        selectionStart: input.selectionStart,
                        selectionEnd: input.selectionEnd,
                        cursorPosition: this.getCursorPosition(input)
                    };

                    Reactive.sendAction(this.id, action, params);
                };

                /** 
                * input.addEventListener('input', (e) => {
                *   if (this._pendingFocus && this._pendingFocus.id === input.id) {
                *         this._pendingFocus.cursorPosition = this.getCursorPosition(input);
                *         this._pendingFocus.value = input.value;
                *     }
                * });
                */
                
                const debouncedHandler = debounce(handleInputEvent, 200);

                if (inputTypes.checked.includes(input.type.toLowerCase())) {
                    input.addEventListener('change', handleInputEvent);
                }
                else {
                    input.addEventListener('input', debouncedHandler);
                    input.addEventListener('change', handleInputEvent);
                }


                input._hasReactiveBinding = true;
            }
        });
    }

    async update(html, state = {}) {
        if (state) {
            this.state = state;
            this.el.dataset.reactiveState = JSON.stringify(state);
        }

        const focusInfo = document.activeElement && this.el.contains(document.activeElement)
            ? {
                id: document.activeElement.id,
                name: document.activeElement.name,
                tagName: document.activeElement.tagName,
                type: document.activeElement.type,
                value: document.activeElement.value,
                selectionStart: document.activeElement.selectionStart,
                selectionEnd: document.activeElement.selectionEnd,
                cursorPosition: this.getCursorPosition(document.activeElement)
            } : this._pendingFocus || null;

        const componentRect = this.el.getBoundingClientRect();

        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');

        const newInner = doc.body.firstElementChild;

        if (newInner) {
            this.el.innerHTML = newInner.innerHTML;
        }

        await new Promise(resolve => requestAnimationFrame(resolve));
        this.bindEvents();

        if (focusInfo) await this.restoreFocus(focusInfo);

        const newRect = this.el.getBoundingClientRect();
        if (componentRect.top !== newRect.top) {
            window.scrollBy(0, newRect.top - componentRect.top);
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


    async restoreFocus(focusInfo) {
        if (!focusInfo) return;

        let elementToFocus = null;

        if (focusInfo.id) {
            elementToFocus = document.getElementById(focusInfo.id);
        }

        if (!elementToFocus && focusInfo.name) {
            const candidates = document.getElementsByName(focusInfo.name);
            if (candidates.length > 0) {
                if (focusInfo.type === 'radio') {
                    elementToFocus = Array.from(candidates).find(el =>
                        el.value === focusInfo.value
                    ) || candidates[0];
                } else {
                    elementToFocus = candidates[0];
                }
            }
        }

        if (elementToFocus && this.el.contains(elementToFocus)) {

            await new Promise(resolve => {
                const check = () => {
                    if (document.body.contains(elementToFocus)) {
                        elementToFocus.focus();
                        resolve();
                    } else {
                        requestAnimationFrame(check);
                    }
                };
                check();
            });


            if (focusInfo.cursorPosition) {
                if (focusInfo.cursorPosition.type === 'input') {

                    try {
                        elementToFocus.setSelectionRange(
                            focusInfo.cursorPosition.start,
                            focusInfo.cursorPosition.end,
                            focusInfo.cursorPosition.direction
                        );
                    } catch (e) {
                        console.debug('Cursor position restore failed', e);
                    }
                } else if (focusInfo.cursorPosition.type === 'contenteditable') {

                    const selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(focusInfo.cursorPosition.range);
                }
            }


            if (focusInfo.value !== undefined &&
                elementToFocus.value !== focusInfo.value) {
                elementToFocus.value = focusInfo.value;


                if (focusInfo.cursorPosition?.type === 'input') {
                    elementToFocus.setSelectionRange(
                        focusInfo.cursorPosition.start,
                        focusInfo.cursorPosition.end,
                        focusInfo.cursorPosition.direction
                    );
                }
            }
        }
    }

}

document.addEventListener('DOMContentLoaded', () => Reactive.init());