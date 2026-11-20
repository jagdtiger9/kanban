import Nora from '@primeuix/themes/nora'
import { definePreset } from '@primeuix/themes'

// Nora preset shared with shop/albion: primary → sky (brand), light surfaces → zinc,
// compact 1px focus ring. Deliberately no `button`/`panelmenu` overrides (kanban's
// plain buttons stay primary-colored, panelmenu is unused).
export const themePreset = definePreset(Nora, {
    semantic: {
        focusRing: {
            width: '1px',
            style: 'solid',
            color: '{primary.color}',
            offset: '1px',
        },
        primary: {
            50: '{sky.50}',
            100: '{sky.100}',
            200: '{sky.200}',
            300: '{sky.300}',
            400: '{sky.400}',
            500: '{sky.500}',
            600: '{sky.600}',
            700: '{sky.700}',
            800: '{sky.800}',
            900: '{sky.900}',
            950: '{sky.950}',
        },
        formField: {
            border: {
                radius: '{border.radius.sm}',
                color: '{surface.0}',
            },
            padding: {
                y: '0.5rem',
            },
        },
        colorScheme: {
            light: {
                content: {
                    borderColor: '{surface.300}',
                },
                formField: {
                    borderColor: '{primary.400}',
                    hoverBorderColor: '{primary.color}',
                    activeBorderColor: '{primary.200}',
                },
                surface: {
                    0: '#ffffff',
                    50: '{zinc.50}',
                    100: '{zinc.100}',
                    200: '{zinc.200}',
                    300: '{zinc.300}',
                    400: '{zinc.400}',
                    500: '{zinc.500}',
                    600: '{zinc.600}',
                    700: '{zinc.700}',
                    800: '{zinc.800}',
                    900: '{zinc.900}',
                    950: '{zinc.950}',
                },
            },
            dark: {
                content: {
                    borderColor: '{surface.600}',
                },
                // denser modal backdrop: the board behind a dialog must recede
                mask: {
                    background: 'rgba(0,0,0,0.6)',
                },
                formField: {
                    borderColor: '{surface.500}',
                    hoverBorderColor: '{primary.color}',
                    activeBorderColor: '{primary.200}',
                },
            },
        },
    },
    // Compact underline tabs instead of Nora's filled primary block: active tab is
    // strong text plus a 2px brand underline, hover only shifts the text color.
    components: {
        tabs: {
            tab: {
                padding: '0.5rem 0.75rem',
                fontWeight: '600',
                hoverBackground: 'transparent',
                activeBackground: 'transparent',
                activeColor: '{text.color}',
                borderWidth: '0 0 2px 0',
                borderColor: 'transparent',
                hoverBorderColor: 'transparent',
                activeBorderColor: '{primary.color}',
            },
            tabpanel: {
                padding: '0.75rem 0 0',
            },
        },
    },
})
