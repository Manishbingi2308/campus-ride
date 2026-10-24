/**
 * CampusRide - Client Interaction Script
 */

document.addEventListener('DOMContentLoaded', () => {
  // Confirm actions with class .confirm-action
  document.querySelectorAll('.confirm-action').forEach(el => {
    el.addEventListener('click', e => {
      const msg = el.getAttribute('data-confirm') || 'Are you sure you want to proceed?';
      if (!confirm(msg)) {
        e.preventDefault();
      }
    });
  });

  // Auto-dismiss success flash messages after 6 seconds
  document.querySelectorAll('.alert-success, .msg.success').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity 0.4s ease';
      el.style.opacity = '0';
      setTimeout(() => el.remove(), 400);
    }, 6000);
  });
});
