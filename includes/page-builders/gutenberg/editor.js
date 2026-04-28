
(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { useBlockProps, InspectorControls } = wp.blockEditor;
    const { PanelBody, SelectControl, Placeholder, Spinner, SearchControl } = wp.components;
    const { useState, useEffect, Fragment, useMemo } = wp.element;
    const { __ } = wp.i18n;

    const BRAND_PRIMARY = '#ff4b93';
    const BRAND_SECONDARY = '#202a8d';

    // Helper function to create form icon SVG (clipboard with form fields)
    const createFormIcon = (size, color) => wp.element.createElement('svg',
        {
            xmlns: 'http://www.w3.org/2000/svg',
            viewBox: '0 0 24 24',
            width: size || 16,
            height: size || 16,
            fill: color || '#fff'
        },
        wp.element.createElement('path', {
            d: 'M19 3h-4.18C14.4 1.84 13.3 1 12 1c-1.3 0-2.4.84-2.82 2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm-7 0c.55 0 1 .45 1 1s-.45 1-1 1-1-.45-1-1 .45-1 1-1zm-2 14H7v-2h3v2zm0-4H7v-2h3v2zm0-4H7V7h3v2zm7 8h-4v-2h4v2zm0-4h-4v-2h4v2zm0-4h-4V7h4v2z'
        })
    );

    // Helper function to create location icon SVG
    const createLocationIcon = (size, color) => wp.element.createElement('svg',
        {
            xmlns: 'http://www.w3.org/2000/svg',
            viewBox: '0 0 24 24',
            width: size || 16,
            height: size || 16,
            fill: color || '#fff'
        },
        wp.element.createElement('path', {
            d: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z'
        })
    );

    // SVG icon element for proper Gutenberg rendering
    const blockIcon = wp.element.createElement('svg',
        {
            xmlns: 'http://www.w3.org/2000/svg',
            viewBox: '0 0 100.47 98.17',
            width: 24,
            height: 24
        },
        wp.element.createElement('path',
            {
                fill: BRAND_PRIMARY,
                d: 'M99.5 16.2c0-1.5-.09-3-.26-4.48-.22-1.83-1.49-2.55-3.21-1.89a13.8 13.8 0 0 0-1.71.88Q73.5 22.25 52.7 33.82a3.84 3.84 0 0 1-4.1 0Q26.74 22.8 4.86 11.96c-3.3-1.65-4.69-.82-4.7 2.79q0 15.06 0 30.12c0 8 0 16.09-.11 24.13 0 1.94-.23 3.58 1.9 4.66 15.76 7.94 31.48 16 47.23 23.93 2.59 1.31 4.15.34 4.2-2.56s-.07-6 0-9a4.3 4.3 0 0 0-2.79-4.48c-9.25-4.58-18.43-9.27-27.62-14-.71-.36-1.73-1-1.79-1.57a65.1 65.1 0 0 1-.08-6.62c1 .42 1.66.65 2.27 1 6.29 3.16 12.57 6.36 18.88 9.5 2.75 1.37 4.24.44 4.28-2.61s-.07-5.84 0-8.75a3.84 3.84 0 0 0-2.5-4c-7.09-3.47-14.13-7.06-21.16-10.65-.65-.33-1.61-.88-1.66-1.42a54.54 54.54 0 0 1-.08-5.93c1.19.57 2 .92 2.71 1.29 8.21 4.11 16.44 8.16 24.6 12.36a5.07 5.07 0 0 0 5.37-.15c7.37-4.16 14.82-8.18 22.25-12.24.78-.43 1.59-.78 2.8-1.38 0 2.22-.11 4 0 5.74a2.88 2.88 0 0 1-1.85 3.15c-6.54 3.46-13 7.07-19.55 10.52a4 4 0 0 0-2.41 4.06c.12 3 .05 6 .1 9s1.45 4 4.21 2.58c3.21-1.69 6.39-3.45 9.59-5.16s6.49-3.45 10.06-5.35c.07 1 .14 1.46.15 2 0 6.54 0 13.09.12 19.64 0 3.19 1.74 4.09 4.42 2.43 4.66-2.88 9.25-5.88 14-8.66a5 5 0 0 0 2.72-5c-.73-18.33-1-36.69-1.36-55.05zm-62.72 2.42c0 .82 1.1 1.92 2 2.38 3.64 1.91 7.38 3.64 11.1 5.4a16.81 16.81 0 0 0 2 .67 13.8 13.8 0 0 0 1.61-.57c6.84-3.63 13.69-7.23 20.47-11a3.36 3.36 0 0 0 1.53-2.45c0-.7-1-1.69-1.79-2-3.55-1.6-7.17-3.1-10.82-4.46a4.45 4.45 0 0 0-3 .09q-10.63 4.65-21.16 9.56a3.55 3.55 0 0 0-1.94 2.38zM20.5 12.01c2.74 1.49 5.6 2.76 8.43 4.09a15.41 15.41 0 0 0 1.73.59 10.77 10.77 0 0 0 1.33-.4c5.68-2.61 11.37-5.18 17-7.9a2.7 2.7 0 0 0 1.36-2.09 3.15 3.15 0 0 0-1.57-2.2q-4.62-2.16-9.43-3.91a4.21 4.21 0 0 0-2.85.14c-5.35 2.29-10.67 4.65-15.94 7.13a2.35 2.35 0 0 0-.06 4.55z'
            }
        )
    );

    registerBlockType('easy-form-builder/form', {
        icon: blockIcon,

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { formId, formName } = attributes;
            const [forms, setForms] = useState([]);
            const [isLoading, setIsLoading] = useState(true);
            const [searchTerm, setSearchTerm] = useState('');
            const [previewHtml, setPreviewHtml] = useState('');

            const blockProps = useBlockProps({
                className: 'efb-gutenberg-block'
            });

            useEffect(() => {
                setIsLoading(true);

                wp.apiFetch({
                    path: '/efb/v1/forms'
                }).then(response => {
                    if (response && response.forms) {
                        setForms(response.forms);
                    }
                    setIsLoading(false);
                }).catch(error => {
                    setIsLoading(false);
                });
            }, []);

            useEffect(() => {
                if (formId) {
                    wp.apiFetch({
                        path: '/efb/v1/preview/' + formId
                    }).then(response => {
                        if (response && response.preview) {
                            setPreviewHtml(response.preview);
                        }
                    }).catch(error => {
                    });
                }
            }, [formId]);

            const onFormChange = (newFormId) => {
                const selectedForm = forms.find(f => String(f.id) === String(newFormId));
                setAttributes({
                    formId: newFormId,
                    formName: selectedForm ? selectedForm.name : ''
                });
            };

            const onFormSelect = (form) => {
                setAttributes({
                    formId: String(form.id),
                    formName: form.name
                });
            };

            // Filter forms based on search term
            const filteredForms = useMemo(() => {
                if (!searchTerm) return forms;
                const term = searchTerm.toLowerCase();
                return forms.filter(form =>
                    form.name.toLowerCase().includes(term) ||
                    String(form.id).includes(term)
                );
            }, [forms, searchTerm]);

            const formOptions = [
                { value: '', label: __('— Select a Form —', 'easy-form-builder') }
            ];

            forms.forEach(form => {
                formOptions.push({
                    value: String(form.id),
                    label: form.name
                });
            });

            // Inspector panel for sidebar settings
            const inspectorControls = wp.element.createElement(
                InspectorControls,
                null,
                wp.element.createElement(
                    PanelBody,
                    {
                        title: __('Form Settings', 'easy-form-builder'),
                        initialOpen: true
                    },
                    isLoading
                        ? wp.element.createElement(
                            'div',
                            { style: { textAlign: 'center', padding: '20px' } },
                            wp.element.createElement(Spinner),
                            wp.element.createElement('p', null, __('Loading forms...', 'easy-form-builder'))
                        )
                        : wp.element.createElement(
                            SelectControl,
                            {
                                label: __('Select Form', 'easy-form-builder'),
                                value: formId,
                                options: formOptions,
                                onChange: onFormChange,
                                help: __('Choose a form to display on your page.', 'easy-form-builder')
                            }
                        )
                ),
                formId && wp.element.createElement(
                    PanelBody,
                    {
                        title: __('Quick Actions', 'easy-form-builder'),
                        initialOpen: false
                    },
                    wp.element.createElement(
                        'div',
                        { style: { display: 'flex', flexDirection: 'column', gap: '8px' } },
                        wp.element.createElement(
                            'button',
                            {
                                className: 'components-button is-secondary',
                                onClick: () => setAttributes({ formId: '', formName: '' }),
                                style: { justifyContent: 'center' }
                            },
                            __('Change Form', 'easy-form-builder')
                        )
                    )
                )
            );

            let blockContent;

            if (isLoading) {
                blockContent = wp.element.createElement(
                    'div',
                    {
                        className: 'efb-block-loading',
                        style: {
                            background: 'linear-gradient(135deg, ' + BRAND_SECONDARY + ' 0%, ' + BRAND_PRIMARY + ' 100%)',
                            padding: '40px',
                            borderRadius: '12px',
                            textAlign: 'center',
                            color: '#fff'
                        }
                    },
                    wp.element.createElement(Spinner),
                    wp.element.createElement('p', { style: { marginTop: '10px', marginBottom: 0 } }, __('Loading forms...', 'easy-form-builder'))
                );
            } else if (!formId) {
                // User-friendly form selection UI
                blockContent = wp.element.createElement(
                    'div',
                    {
                        className: 'efb-block-selector',
                        style: {
                            background: '#fff',
                            borderRadius: '12px',
                            overflow: 'hidden',
                            border: '1px solid #e0e0e0',
                            boxShadow: '0 2px 8px rgba(0,0,0,0.08)'
                        }
                    },
                    // Header
                    wp.element.createElement(
                        'div',
                        {
                            style: {
                                background: 'linear-gradient(135deg, ' + BRAND_SECONDARY + ' 0%, ' + BRAND_PRIMARY + ' 100%)',
                                padding: '20px',
                                textAlign: 'center',
                                color: '#fff'
                            }
                        },
                        wp.element.createElement(
                            'div',
                            { style: { marginBottom: '8px' } },
                            blockIcon
                        ),
                        wp.element.createElement(
                            'h3',
                            { style: { margin: '0 0 5px 0', fontSize: '18px', fontWeight: '600' } },
                            __('Easy Form Builder', 'easy-form-builder')
                        ),
                        wp.element.createElement(
                            'p',
                            { style: { margin: 0, opacity: 0.9, fontSize: '13px' } },
                            __('Select a form to display on your page', 'easy-form-builder')
                        )
                    ),
                    // Search and forms list
                    wp.element.createElement(
                        'div',
                        { style: { padding: '20px' } },
                        // Search input
                        forms.length > 5 && wp.element.createElement(
                            'div',
                            { style: { marginBottom: '15px' } },
                            wp.element.createElement(
                                'input',
                                {
                                    type: 'text',
                                    placeholder: __('Search forms...', 'easy-form-builder'),
                                    value: searchTerm,
                                    onChange: (e) => setSearchTerm(e.target.value),
                                    style: {
                                        width: '100%',
                                        padding: '10px 15px',
                                        border: '1px solid #ddd',
                                        borderRadius: '6px',
                                        fontSize: '14px',
                                        outline: 'none'
                                    }
                                }
                            )
                        ),
                        // Forms grid
                        filteredForms.length > 0 ? wp.element.createElement(
                            'div',
                            {
                                style: {
                                    display: 'grid',
                                    gridTemplateColumns: 'repeat(auto-fill, minmax(200px, 1fr))',
                                    gap: '10px',
                                    maxHeight: '300px',
                                    overflowY: 'auto',
                                    padding: '5px'
                                }
                            },
                            filteredForms.map(form => wp.element.createElement(
                                'button',
                                {
                                    key: form.id,
                                    onClick: () => onFormSelect(form),
                                    style: {
                                        display: 'flex',
                                        alignItems: 'center',
                                        gap: '10px',
                                        padding: '12px 15px',
                                        background: form.type === 'tracking' ? 'linear-gradient(135deg, #f0f4ff 0%, #e8f0ff 100%)' : '#f8f9fa',
                                        border: '1px solid ' + (form.type === 'tracking' ? BRAND_SECONDARY : '#e0e0e0'),
                                        borderRadius: '8px',
                                        cursor: 'pointer',
                                        textAlign: 'left',
                                        width: '100%',
                                        transition: 'all 0.2s ease'
                                    },
                                    onMouseEnter: (e) => {
                                        e.currentTarget.style.borderColor = BRAND_PRIMARY;
                                        e.currentTarget.style.transform = 'translateY(-2px)';
                                        e.currentTarget.style.boxShadow = '0 4px 12px rgba(255, 75, 147, 0.15)';
                                    },
                                    onMouseLeave: (e) => {
                                        e.currentTarget.style.borderColor = form.type === 'tracking' ? BRAND_SECONDARY : '#e0e0e0';
                                        e.currentTarget.style.transform = 'translateY(0)';
                                        e.currentTarget.style.boxShadow = 'none';
                                    }
                                },
                                wp.element.createElement(
                                    'div',
                                    {
                                        style: {
                                            width: '28px',
                                            height: '28px',
                                            display: 'flex',
                                            alignItems: 'center',
                                            justifyContent: 'center',
                                            background: form.type === 'tracking' ? BRAND_SECONDARY : BRAND_PRIMARY,
                                            color: '#fff',
                                            borderRadius: '6px',
                                            flexShrink: 0
                                        }
                                    },
                                    form.type === 'tracking' ? createLocationIcon(16, '#fff') : createFormIcon(16, '#fff')
                                ),
                                wp.element.createElement(
                                    'span',
                                    {
                                        style: {
                                            flex: 1,
                                            overflow: 'hidden',
                                            textOverflow: 'ellipsis',
                                            whiteSpace: 'nowrap',
                                            fontSize: '13px',
                                            fontWeight: '500',
                                            color: '#333'
                                        }
                                    },
                                    form.type === 'tracking'
                                        ? form.name
                                        : form.name + ' (' + __('Form Code', 'easy-form-builder') + ': ' + form.id + ')'
                                )
                            ))
                        ) : wp.element.createElement(
                            'div',
                            { style: { textAlign: 'center', padding: '30px', color: '#666' } },
                            searchTerm
                                ? __('No forms found matching your search.', 'easy-form-builder')
                                : __('No forms available. Create a form first.', 'easy-form-builder')
                        )
                    )
                );
            } else {
                // Selected form preview
                blockContent = wp.element.createElement(
                    'div',
                    { className: 'efb-block-preview' },
                    wp.element.createElement(
                        'div',
                        {
                            className: 'efb-block-preview-header',
                            style: {
                                background: 'linear-gradient(135deg, ' + BRAND_SECONDARY + ' 0%, ' + BRAND_PRIMARY + ' 100%)',
                                padding: '20px',
                                borderRadius: '12px 12px 0 0',
                                display: 'flex',
                                alignItems: 'center',
                                justifyContent: 'space-between',
                                color: '#fff'
                            }
                        },
                        wp.element.createElement(
                            'div',
                            { style: { display: 'flex', alignItems: 'center', gap: '12px' } },
                            wp.element.createElement(
                                'div',
                                {
                                    style: {
                                        width: '40px',
                                        height: '40px',
                                        display: 'flex',
                                        alignItems: 'center',
                                        justifyContent: 'center',
                                        background: 'rgba(255,255,255,0.2)',
                                        borderRadius: '8px'
                                    }
                                },
                                formId === 'tracking' ? createLocationIcon(24, '#fff') : createFormIcon(24, '#fff')
                            ),
                            wp.element.createElement(
                                'div',
                                null,
                                wp.element.createElement(
                                    'div',
                                    { style: { fontSize: '16px', fontWeight: '600', marginBottom: '2px' } },
                                    formName || __('Form', 'easy-form-builder')
                                ),
                                wp.element.createElement(
                                    'div',
                                    { style: { fontSize: '12px', opacity: 0.8 } },
                                    'Easy Form Builder'
                                )
                            )
                        ),
                        wp.element.createElement(
                            'button',
                            {
                                onClick: () => setAttributes({ formId: '', formName: '' }),
                                style: {
                                    background: 'rgba(255,255,255,0.2)',
                                    border: 'none',
                                    color: '#fff',
                                    padding: '8px 12px',
                                    borderRadius: '6px',
                                    cursor: 'pointer',
                                    fontSize: '12px',
                                    fontWeight: '500'
                                }
                            },
                            __('Change', 'easy-form-builder')
                        )
                    ),
                    wp.element.createElement(
                        'div',
                        {
                            className: 'efb-block-preview-content',
                            style: {
                                padding: '25px',
                                background: '#f8f9fa',
                                borderRadius: '0 0 12px 12px',
                                border: '1px solid #e9ecef',
                                borderTop: 'none',
                                textAlign: 'center'
                            }
                        },
                        wp.element.createElement(
                            'div',
                            {
                                style: {
                                    background: '#fff',
                                    border: '2px dashed #ddd',
                                    borderRadius: '8px',
                                    padding: '30px 20px',
                                    color: '#666'
                                }
                            },
                            wp.element.createElement(
                                'p',
                                { style: { margin: '0 0 10px 0', fontSize: '14px' } },
                                __('Form will be displayed here on the frontend.', 'easy-form-builder')
                            ),
                            wp.element.createElement(
                                'code',
                                {
                                    style: {
                                        display: 'inline-block',
                                        background: '#f1f3f5',
                                        padding: '8px 14px',
                                        borderRadius: '4px',
                                        fontSize: '12px',
                                        color: BRAND_SECONDARY,
                                        fontFamily: 'monospace'
                                    }
                                },
                                formId === 'tracking'
                                    ? '[Easy_Form_Builder_confirmation_code_finder]'
                                    : '[EMS_Form_Builder id="' + formId + '"]'
                            )
                        )
                    )
                );
            }

            return wp.element.createElement(
                Fragment,
                null,
                inspectorControls,
                wp.element.createElement(
                    'div',
                    blockProps,
                    blockContent
                )
            );
        },

        save: function() {
            return null;
        }
    });

})(window.wp);
