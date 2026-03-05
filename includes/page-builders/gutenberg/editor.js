
(function(wp) {
    const { registerBlockType } = wp.blocks;
    const { useBlockProps, InspectorControls } = wp.blockEditor;
    const { PanelBody, SelectControl, Placeholder, Spinner } = wp.components;
    const { useState, useEffect, Fragment } = wp.element;
    const { __ } = wp.i18n;

    const BRAND_PRIMARY = '#ff4b93';
    const BRAND_SECONDARY = '#202a8d';

    const blockIcon = wp.element.createElement('img',
        {
            src: (typeof efbBlockData !== 'undefined' && efbBlockData.logoUrl)
                ? efbBlockData.logoUrl
                : '/wp-content/plugins/easy-form-builder/includes/admin/assets/image/logo.svg',
            alt: 'Easy Form Builder',
            width: 24,
            height: 24,
            style: { display: 'block', margin: '0px 5px' }
        }
    );

    registerBlockType('easy-form-builder/form', {
        icon: blockIcon,

        edit: function(props) {
            const { attributes, setAttributes } = props;
            const { formId, formName } = attributes;
            const [forms, setForms] = useState([]);
            const [isLoading, setIsLoading] = useState(true);
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

            const formOptions = [
                { value: '', label: __('— Select a Form —', 'easy-form-builder') }
            ];

            forms.forEach(form => {
                formOptions.push({
                    value: String(form.id),
                    label: form.name
                });
            });

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
                )
            );

            let blockContent;

            if (isLoading) {
                blockContent = wp.element.createElement(
                    Placeholder,
                    {
                        icon: blockIcon,
                        label: __('Easy Form Builder', 'easy-form-builder')
                    },
                    wp.element.createElement(Spinner),
                    wp.element.createElement('p', null, __('Loading forms...', 'easy-form-builder'))
                );
            } else if (!formId) {
                blockContent = wp.element.createElement(
                    Placeholder,
                    {
                        icon: blockIcon,
                        label: __('Easy Form Builder', 'easy-form-builder'),
                        instructions: __('Select a form to display from the dropdown below.', 'easy-form-builder')
                    },
                    wp.element.createElement(
                        SelectControl,
                        {
                            value: formId,
                            options: formOptions,
                            onChange: onFormChange,
                            style: { width: '100%', maxWidth: '300px' }
                        }
                    )
                );
            } else {
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
                                textAlign: 'center',
                                color: '#fff'
                            }
                        },
                        wp.element.createElement(
                            'div',
                            { style: { fontSize: '18px', fontWeight: '700', marginBottom: '8px' } },
                            __('Easy Form Builder', 'easy-form-builder')
                        ),
                        wp.element.createElement(
                            'div',
                            {
                                style: {
                                    fontSize: '14px',
                                    background: 'rgba(255,255,255,0.15)',
                                    padding: '10px 15px',
                                    borderRadius: '6px',
                                    display: 'inline-block'
                                }
                            },
                            wp.element.createElement('span', { style: { opacity: 0.8 } }, __('Selected Form: ', 'easy-form-builder')),
                            wp.element.createElement('strong', null, formName || formId)
                        )
                    ),
                    wp.element.createElement(
                        'div',
                        {
                            className: 'efb-block-preview-content',
                            style: {
                                padding: '20px',
                                background: '#f8f9fa',
                                borderRadius: '0 0 12px 12px',
                                border: '1px solid #e9ecef',
                                borderTop: 'none',
                                textAlign: 'center',
                                color: '#666'
                            }
                        },
                        wp.element.createElement(
                            'p',
                            { style: { margin: 0 } },
                            __('Form will be displayed here on the frontend.', 'easy-form-builder')
                        ),
                        wp.element.createElement(
                            'code',
                            {
                                style: {
                                    display: 'inline-block',
                                    marginTop: '10px',
                                    background: '#fff',
                                    padding: '6px 12px',
                                    borderRadius: '4px',
                                    fontSize: '12px',
                                    border: '1px solid #ddd'
                                }
                            },
                            formId === 'tracking'
                                ? '[Easy_Form_Builder_confirmation_code_finder]'
                                : '[EMS_Form_Builder id="' + formId + '"]'
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
