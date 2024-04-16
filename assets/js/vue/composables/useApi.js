// Composables/useApi.js
import axios from 'axios';

export default function useApi() {
  const makePayload = (payloadData = {}) => {
    const payload = new FormData();
    Object.keys(payloadData).forEach((key) => {
      payload.append(key, payloadData[key]);
    });
    return payload;
  };

  const triggerAction = async (action, data = {}) => {
    console.log('Triggering action:', action, 'with data:', data);
    const payload = makePayload({
      action,
      ...data,
    });
    return await axios.post(
      otisDash.ajax_url,
      payload,
      { timeout: 0 }
    );
  };

  return { triggerAction };
}