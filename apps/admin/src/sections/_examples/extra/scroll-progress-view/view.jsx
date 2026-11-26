'use client';

import Box from '@mui/material/Box';
import Paper from '@mui/material/Paper';
import Stack from '@mui/material/Stack';
import CardContent from '@mui/material/CardContent';

import { paths } from 'src/routes/paths';

import { varAlpha } from 'src/theme/styles';

import { Scrollbar } from 'src/components/scrollbar';
import { CustomBreadcrumbs } from 'src/components/custom-breadcrumbs';
import { ScrollProgress, useScrollProgress } from 'src/components/animate/scroll-progress';

import { ComponentHero } from '../../component-hero';
import { ComponentBlock, ComponentContainer } from '../../component-block';

// ----------------------------------------------------------------------

export function ScrollProgressView() {
  const [, , pageScrollYProgress] = useScrollProgress();

  const [containerElementRef1, , containerScrollYProgress] = useScrollProgress('container');

  const [containerElementRef2, containerScrollXProgress] = useScrollProgress('container');

  return (
    <>
      <ScrollProgress
        variant="linear"
        size={6}
        progress={pageScrollYProgress}
        sx={{ position: 'fixed' }}
      />

      <ComponentHero>
        <CustomBreadcrumbs
          heading="Scroll progress"
          links={[{ name: 'Components', href: paths.components }, { name: 'Scroll progress' }]}
        />
      </ComponentHero>

      <ComponentContainer>
        <ComponentBlock
          title="Container scroll Y"
          sx={{ flexDirection: 'column', alignItems: 'unset' }}
        >
          <ScrollProgress
            color="info"
            variant="circular"
            size={40}
            thickness={2}
            progress={containerScrollYProgress}
            sx={{ position: 'absolute', top: 16, right: 16 }}
          />

          <ScrollProgress
            variant="linear"
            color="info"
            progress={containerScrollYProgress}
          />

          <CardContent>
            <Scrollbar ref={containerElementRef1} sx={{ height: 480 }}>
              <Stack spacing={3}>
                {[...Array(12)].map((_, index) => (
                  <Paper
                    key={index}
                    variant="outlined"
                    sx={{
                      width: 1,
                      height: 160,
                      flexShrink: 0,
                      borderRadius: 1.5,
                      display: 'flex',
                      typography: 'h2',
                      alignItems: 'center',
                      justifyContent: 'center',
                      color: (theme) => varAlpha(theme.vars.palette.grey['500Channel'], 0.24),
                    }}
                  >
                    {index + 1}
                  </Paper>
                ))}
              </Stack>
            </Scrollbar>
          </CardContent>
        </ComponentBlock>

        <ComponentBlock
          title="Container scroll X"
          sx={{ flexDirection: 'column', alignItems: 'unset' }}
        >
          <ScrollProgress
            color="inherit"
            variant="circular"
            size={40}
            thickness={2}
            progress={containerScrollXProgress}
            sx={{ position: 'absolute', top: 8, right: 8 }}
          />

          <ScrollProgress
            variant="linear"
            color="inherit"
            progress={containerScrollXProgress}
          />

          <CardContent>
            <Scrollbar ref={containerElementRef2}>
              <Stack direction="row" spacing={3}>
                {[...Array(24)].map((_, index) => (
                  <Paper
                    key={index}
                    variant="outlined"
                    sx={{
                      width: 200,
                      flexShrink: 0,
                      borderRadius: 1.5,
                      display: 'flex',
                      typography: 'h2',
                      aspectRatio: '9/16',
                      alignItems: 'center',
                      justifyContent: 'center',
                      color: (theme) => varAlpha(theme.vars.palette.grey['500Channel'], 0.24),
                    }}
                  >
                    {index + 1}
                  </Paper>
                ))}
              </Stack>
            </Scrollbar>
          </CardContent>
        </ComponentBlock>

        <Box
          sx={{
            my: 10,
            minHeight: 2000,
            typography: 'h6',
            textAlign: 'center',
          }}
        >
          👇 Scroll down document
        </Box>
      </ComponentContainer>
    </>
  );
}
