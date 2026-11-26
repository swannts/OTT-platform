import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';

export function RbacPageHeader({ title, description, action }) {
  return (
    <Box
      sx={{
        mb: 3,
        display: 'flex',
        alignItems: { sm: 'center' },
        justifyContent: 'space-between',
        gap: 2,
        flexWrap: 'wrap',
      }}
    >
      <Box>
        <Typography variant="h4">{title}</Typography>
        <Typography variant="body2" color="text.secondary" sx={{ mt: 0.5 }}>
          {description}
        </Typography>
      </Box>
      {action}
    </Box>
  );
}
