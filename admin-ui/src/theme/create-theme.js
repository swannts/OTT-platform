import { experimental_extendTheme as extendTheme } from '@mui/material/styles';
import { isValidElement } from 'react';

import { setFont } from './styles/utils';
import { overridesTheme } from './overrides-theme';
import { shadows, typography, components, colorSchemes, customShadows } from './core';
import { updateCoreWithSettings, updateComponentsWithSettings } from './with-settings/update-theme';

// ----------------------------------------------------------------------

export function createTheme(localeComponents, settings) {
  const initialTheme = {
    colorSchemes,
    shadows: shadows(settings.colorScheme),
    customShadows: customShadows(settings.colorScheme),
    direction: settings.direction,
    shape: { borderRadius: 8 },
    components,
    typography: {
      ...typography,
      fontFamily: setFont(settings.fontFamily),
    },
    cssVarPrefix: '',
    shouldSkipGeneratingVar,
  };

  /**
   * 1.Update values from settings before creating theme.
   */
  const updateTheme = updateCoreWithSettings(initialTheme, settings);

  // React development elements hold a circular `_owner` fiber reference. MUI's
  // theme deep merge walks component defaults, so keep those elements out of
  // the merge and restore them after the theme has been composed.
  const elementDefaults = [];
  const themeInputs = [
    { ...updateTheme, components: updateTheme.components },
    localeComponents,
    updateComponentsWithSettings(settings),
    overridesTheme,
  ].map((themeInput) => {
    if (!themeInput?.components) return themeInput;

    const safeComponents = Object.fromEntries(
      Object.entries(themeInput.components).map(([componentName, componentConfig]) => {
        const defaultProps = componentConfig?.defaultProps;
        if (!defaultProps) return [componentName, componentConfig];

        const mergeSafeDefaults = stripElements(defaultProps, (path, value) => {
          elementDefaults.push({ componentName, path, value });
        });

        return [componentName, { ...componentConfig, defaultProps: mergeSafeDefaults }];
      })
    );

    return { ...themeInput, components: safeComponents };
  });

  /**
   * 2.Create theme + add locale + update component with settings.
   */
  const theme = extendTheme(...themeInputs);

  for (const { componentName, path, value } of elementDefaults) {
    const parent = path
      .slice(0, -1)
      .reduce((current, key) => current[key], theme.components[componentName].defaultProps);
    parent[path.at(-1)] = value;
  }

  return theme;
}

function stripElements(value, onElement, path = []) {
  if (isValidElement(value)) {
    onElement(path, value);
    return undefined;
  }

  if (Array.isArray(value)) {
    return value.map((item, index) => stripElements(item, onElement, [...path, index]));
  }

  if (value && Object.getPrototypeOf(value) === Object.prototype) {
    return Object.fromEntries(
      Object.entries(value).map(([key, item]) => [
        key,
        stripElements(item, onElement, [...path, key]),
      ])
    );
  }

  return value;
}

// ----------------------------------------------------------------------

function shouldSkipGeneratingVar(keys, value) {
  const skipGlobalKeys = [
    'mixins',
    'overlays',
    'direction',
    'breakpoints',
    'cssVarPrefix',
    'unstable_sxConfig',
    'typography',
    // 'transitions',
  ];

  const skipPaletteKeys = {
    global: ['tonalOffset', 'dividerChannel', 'contrastThreshold'],
    grey: ['A100', 'A200', 'A400', 'A700'],
    text: ['icon'],
  };

  const isPaletteKey = keys[0] === 'palette';

  if (isPaletteKey) {
    const paletteType = keys[1];
    const skipKeys = skipPaletteKeys[paletteType] || skipPaletteKeys.global;

    return keys.some((key) => skipKeys?.includes(key));
  }

  return keys.some((key) => skipGlobalKeys?.includes(key));
}

/**
* createTheme without @settings and @locale components.
*
 ```jsx
export function createTheme(): Theme {
  const initialTheme = {
    colorSchemes,
    shadows: shadows('light'),
    customShadows: customShadows('light'),
    shape: { borderRadius: 8 },
    components,
    typography,
    cssVarPrefix: '',
    shouldSkipGeneratingVar,
  };

  const theme = extendTheme(initialTheme, overridesTheme);

  return theme;
}
 ```
*/
