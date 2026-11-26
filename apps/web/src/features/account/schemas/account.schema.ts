import { z } from 'zod';

export const updateProfileSchema = z.object({
  first_name: z.string().min(1, 'First name is required').max(50, 'Max 50 characters'),
  last_name: z.string().max(50, 'Max 50 characters').optional().or(z.literal('')),
  email: z.string().email('Please enter a valid email address'),
  mobile: z.string().max(20, 'Max 20 characters').optional().or(z.literal('')),
  gender: z.enum(['male', 'female', 'other', '']).optional(),
  date_of_birth: z.string().optional().or(z.literal('')),
});

export type UpdateProfileInput = z.infer<typeof updateProfileSchema>;

export const changePasswordSchema = z
  .object({
    old_password: z.string().min(1, 'Current password is required'),
    new_password: z.string().min(6, 'Password must be at least 6 characters'),
    confirm_password: z.string().min(6, 'Confirm password must match'),
  })
  .refine((data) => data.new_password === data.confirm_password, {
    message: 'New passwords do not match',
    path: ['confirm_password'],
  });

export type ChangePasswordInput = z.infer<typeof changePasswordSchema>;

export const parentalPinSchema = z.object({
  enabled: z.boolean(),
  pin: z.string().regex(/^\d{4}$/, 'PIN must be exactly 4 digits').optional().or(z.literal('')),
});

export type ParentalPinInput = z.infer<typeof parentalPinSchema>;

export const multiProfileSchema = z.object({
  name: z.string().min(1, 'Name is required').max(12, 'Name must be 12 characters or less'),
  is_child_profile: z.boolean().default(false),
  avatar: z.string().optional(),
});

export type MultiProfileInput = z.infer<typeof multiProfileSchema>;
