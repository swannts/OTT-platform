import { useRef, useState, useEffect, useCallback } from 'react';

// ----------------------------------------------------------------------

export function useCountdownDate(date) {
  const [countdown, setCountdown] = useState({
    days: '00',
    hours: '00',
    minutes: '00',
    seconds: '00',
  });

  const setNewTime = useCallback(() => {
    const startTime = date;

    const endTime = new Date();

    const distanceToNow = startTime.valueOf() - endTime.valueOf();

    const getDays = Math.floor(distanceToNow / (1000 * 60 * 60 * 24));

    const getHours =
      `0${Math.floor((distanceToNow % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60))}`.slice(-2);

    const getMinutes = `0${Math.floor((distanceToNow % (1000 * 60 * 60)) / (1000 * 60))}`.slice(-2);

    const getSeconds = `0${Math.floor((distanceToNow % (1000 * 60)) / 1000)}`.slice(-2);

    setCountdown({
      days: getDays < 10 ? `0${getDays}` : `${getDays}`,
      hours: getHours,
      minutes: getMinutes,
      seconds: getSeconds,
    });
  }, [date]);

  useEffect(() => {
    const frame = window.requestAnimationFrame(setNewTime);
    const interval = setInterval(setNewTime, 1000);

    return () => {
      window.cancelAnimationFrame(frame);
      clearInterval(interval);
    };
  }, [setNewTime]);

  return countdown;
}

// Usage
// const countdown = useCountdown(new Date('07/07/2022 21:30'));

// ----------------------------------------------------------------------

export function useCountdownSeconds(initCountdown) {
  const [countdown, setCountdown] = useState(initCountdown);

  const remainingSecondsRef = useRef(countdown);

  const startCountdown = useCallback(() => {
    remainingSecondsRef.current = countdown;

    const intervalId = setInterval(() => {
      remainingSecondsRef.current -= 1;

      if (remainingSecondsRef.current === 0) {
        clearInterval(intervalId);
        setCountdown(initCountdown);
      } else {
        setCountdown(remainingSecondsRef.current);
      }
    }, 1000);
  }, [initCountdown, countdown]);

  const counting = initCountdown > countdown;

  return {
    counting,
    countdown,
    startCountdown,
    setCountdown,
  };
}

// Usage
// const { countdown, startCountdown, counting } = useCountdownSeconds(30);
