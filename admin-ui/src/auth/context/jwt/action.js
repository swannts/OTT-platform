'use client';

import axios, { endpoints } from 'src/utils/axios';


/** **************************************
 * Sign in
 *************************************** */
export const signInWithPassword = async ({ email, password }) => {
  const params = { email, password };
  const res = await axios.post(endpoints.auth.signIn, params);

  return res.data.user;
};

/** **************************************
 * Sign up
 *************************************** */
/** **************************************
 * Sign out
 *************************************** */
export const signOut = async () => {
  try {
    await axios.post(endpoints.auth.signOut);
  } catch (error) {
    throw error;
  }
};
