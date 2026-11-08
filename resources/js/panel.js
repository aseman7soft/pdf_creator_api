/*
 * Admin panel behaviours.
 *
 * Deliberately written as a plain, dependency-free script: it relies on the
 * global `window.bootstrap` object (provided either by the bundled
 * `bootstrap` package below or by `bootstrap.bundle.min.js` in the non-Vite
 * fallback). The identical file is shipped as `public/vendor/panel/panel.js`.
 */
(function () {
    'use strict';

    function ready(callback) {
        if (document.readyState !== 'loading') {
            callback();
        } else {
            document.addEventListener('DOMContentLoaded', callback);
        }
    }

    /* ------------------------------------------------------------ sidebar */

    function initSidebar() {
        var toggle = document.querySelector('[data-sidebar-toggle]');
        var backdrop = document.querySelector('[data-sidebar-backdrop]');

        if (toggle) {
            toggle.addEventListener('click', function () {
                if (window.innerWidth >= 992) {
                    document.body.classList.toggle('sidebar-collapsed');
                } else {
                    document.body.classList.toggle('sidebar-open');
                }
            });
        }

        if (backdrop) {
            backdrop.addEventListener('click', function () {
                document.body.classList.remove('sidebar-open');
            });
        }
    }

    /* ----------------------------------------------------------- tooltips */

    function initTooltips() {
        if (!window.bootstrap || !window.bootstrap.Tooltip) {
            return;
        }

        document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (element) {
            window.bootstrap.Tooltip.getOrCreateInstance(element);
        });
    }

    /* -------------------------------------------------------- confirm form */

    function initConfirmations() {
        document.addEventListener('submit', function (event) {
            var form = event.target;

            if (!(form instanceof HTMLFormElement)) {
                return;
            }

            var message = form.getAttribute('data-confirm');

            if (message && !window.confirm(message)) {
                event.preventDefault();
                event.stopPropagation();
            }
        });

        document.addEventListener('click', function (event) {
            var trigger = event.target.closest('[data-confirm-click]');

            if (!trigger) {
                return;
            }

            if (!window.confirm(trigger.getAttribute('data-confirm-click'))) {
                event.preventDefault();
                event.stopPropagation();
            }
        });
    }

    /* ---------------------------------------------------- auto hide alerts */

    function initAlerts() {
        var alerts = document.querySelectorAll('[data-auto-dismiss]');

        alerts.forEach(function (alert) {
            window.setTimeout(function () {
                alert.classList.remove('show');
                alert.classList.add('fade');
                window.setTimeout(function () {
                    if (alert.parentNode) {
                        alert.parentNode.removeChild(alert);
                    }
                }, 300);
            }, parseInt(alert.getAttribute('data-auto-dismiss'), 10) || 6000);
        });
    }

    /* ---------------------------------------------------------- copy button */

    function copyText(value) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(value);
        }

        return new Promise(function (resolve, reject) {
            var area = document.createElement('textarea');
            area.value = value;
            area.setAttribute('readonly', 'readonly');
            area.style.position = 'absolute';
            area.style.left = '-9999px';
            document.body.appendChild(area);
            area.select();

            try {
                document.execCommand('copy');
                document.body.removeChild(area);
                resolve();
            } catch (error) {
                document.body.removeChild(area);
                reject(error);
            }
        });
    }

    function initCopyButtons() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-copy]');

            if (!button) {
                return;
            }

            event.preventDefault();
            var value = button.getAttribute('data-copy');
            var sourceSelector = button.getAttribute('data-copy-from');

            if (!value && sourceSelector) {
                var source = document.querySelector(sourceSelector);
                value = source ? (source.value || source.textContent || '') : '';
            }

            if (!value) {
                return;
            }

            var original = button.getAttribute('data-original-html') || button.innerHTML;

            copyText(value.trim()).then(function () {
                button.setAttribute('data-original-html', original);
                button.innerHTML = '<i class="bi bi-check2"></i> کپی شد';
                window.setTimeout(function () {
                    button.innerHTML = original;
                }, 1600);
            }).catch(function () {
                window.alert('کپی کردن متن ناموفق بود. لطفاً متن را دستی انتخاب کنید.');
            });
        });
    }

    /* --------------------------------------------------- insert at cursor */

    function insertAtCursor(element, text) {
        if (!element) {
            return;
        }

        if (typeof element.setSelectionRange !== 'function') {
            element.value += text;
            return;
        }

        element.focus();
        var start = element.selectionStart || 0;
        var end = element.selectionEnd || 0;
        var value = element.value;

        element.value = value.substring(0, start) + text + value.substring(end);
        element.selectionStart = element.selectionEnd = start + text.length;
        element.dispatchEvent(new Event('input', { bubbles: true }));
    }

    function initInsertButtons() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-insert]');

            if (!button) {
                return;
            }

            event.preventDefault();
            var targetSelector = button.getAttribute('data-target');
            var target = targetSelector ? document.querySelector(targetSelector) : null;

            if (!target) {
                return;
            }

            var snippet = button.getAttribute('data-insert');
            insertAtCursor(target, snippet);
        });
    }

    /* ------------------------------------------------------ editor tab key */

    function initEditorKeys() {
        document.querySelectorAll('textarea.code-editor').forEach(function (editor) {
            editor.addEventListener('keydown', function (event) {
                if (event.key !== 'Tab') {
                    return;
                }

                event.preventDefault();
                insertAtCursor(editor, '    ');
            });
        });
    }

    /* --------------------------------------------------- dynamic form rows */

    function initDynamicRows() {
        document.addEventListener('click', function (event) {
            var addButton = event.target.closest('[data-row-add]');

            if (addButton) {
                event.preventDefault();
                var container = document.querySelector(addButton.getAttribute('data-row-add'));
                var templateSelector = addButton.getAttribute('data-template');
                var template = templateSelector ? document.querySelector(templateSelector) : null;

                if (!container || !template) {
                    return;
                }

                var index = Number(container.getAttribute('data-next-index') || container.children.length || 0);
                var html = template.innerHTML.replace(/__INDEX__/g, String(index))
                    .replace(/__NUMBER__/g, String(index + 1));
                var wrapper = document.createElement('tbody');
                wrapper.innerHTML = html.trim();

                while (wrapper.firstElementChild) {
                    container.appendChild(wrapper.firstElementChild);
                }

                container.setAttribute('data-next-index', String(index + 1));
                initTooltips();

                return;
            }

            var removeButton = event.target.closest('[data-row-remove]');

            if (removeButton) {
                event.preventDefault();
                var row = removeButton.closest('[data-row]');

                if (row && row.parentNode) {
                    row.parentNode.removeChild(row);
                }
            }
        });
    }

    /* --------------------------------------------------------- password eye */

    function initPasswordToggles() {
        document.addEventListener('click', function (event) {
            var button = event.target.closest('[data-password-toggle]');

            if (!button) {
                return;
            }

            event.preventDefault();
            var input = document.querySelector(button.getAttribute('data-password-toggle'));

            if (!input) {
                return;
            }

            var visible = input.getAttribute('type') === 'text';
            input.setAttribute('type', visible ? 'password' : 'text');
            button.innerHTML = visible ? '<i class="bi bi-eye"></i>' : '<i class="bi bi-eye-slash"></i>';
        });
    }

    /* -------------------------------------------------------- json helpers */

    function initJsonTools() {
        document.querySelectorAll('[data-json-format]').forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                var target = document.querySelector(button.getAttribute('data-json-format'));
                var feedback = document.querySelector(button.getAttribute('data-json-feedback') || '');

                if (!target) {
                    return;
                }

                try {
                    var parsed = JSON.parse(target.value || '{}');
                    target.value = JSON.stringify(parsed, null, 2);

                    if (feedback) {
                        feedback.className = 'form-text text-success';
                        feedback.textContent = 'JSON معتبر است.';
                    }
                } catch (error) {
                    if (feedback) {
                        feedback.className = 'form-text text-danger';
                        feedback.textContent = 'JSON نامعتبر است: ' + error.message;
                    }
                }
            });
        });

        document.querySelectorAll('form[data-validate-json]').forEach(function (form) {
            form.addEventListener('submit', function (event) {
                var invalid = false;

                form.querySelectorAll('textarea[data-json]').forEach(function (area) {
                    if (!area.value.trim()) {
                        return;
                    }

                    try {
                        JSON.parse(area.value);
                    } catch (error) {
                        invalid = true;
                        area.classList.add('is-invalid');
                    }
                });

                if (invalid) {
                    event.preventDefault();
                    window.alert('داده‌های JSON وارد شده معتبر نیستند. لطفاً اصلاح کنید.');
                }
            });
        });
    }

    /* ------------------------------------------------------------- tabs hash */

    function initTabHash() {
        var hash = window.location.hash;

        if (!hash || !window.bootstrap || !window.bootstrap.Tab) {
            return;
        }

        var trigger = document.querySelector('[data-bs-toggle="tab"][data-bs-target="' + hash + '"]');

        if (trigger) {
            window.bootstrap.Tab.getOrCreateInstance(trigger).show();
        }
    }

    /* ------------------------------------------------------------ bootstrap */

    ready(function () {
        initSidebar();
        initTooltips();
        initConfirmations();
        initAlerts();
        initCopyButtons();
        initInsertButtons();
        initEditorKeys();
        initDynamicRows();
        initPasswordToggles();
        initJsonTools();
        initTabHash();
    });
})();
