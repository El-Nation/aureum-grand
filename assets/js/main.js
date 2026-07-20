/**
 * AUREUM HOTEL PLATFORM — main.js
 * Shared interactivity for the public-facing site.
 */

document.addEventListener('DOMContentLoaded', function () {

  // Favorite-room heart toggle — persists via api/toggle-favorite.php
  // when a guest is signed in; otherwise prompts sign-in.
  document.querySelectorAll('.room-card-fav').forEach(function (btn) {
    btn.addEventListener('click', async function (e) {
      e.preventDefault();
      const roomId = this.closest('[data-room-id]')?.dataset.roomId
        || this.closest('.room-card')?.querySelector('a[href*="room-detail.php?id="]')?.href.match(/id=(\d+)/)?.[1];

      if (!roomId) return;

      try {
        const res = await fetch(BASE_URL + '/api/toggle-favorite.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ room_id: roomId })
        });
        const data = await res.json();

        if (data.success) {
          this.classList.toggle('active', data.favorited);
          this.style.color = data.favorited ? '#a14b3f' : '';
        } else if (data.message) {
          if (confirm(data.message + '\n\nGo to sign-in page?')) {
            window.location.href = BASE_URL + '/guest/login.php';
          }
        }
      } catch (err) {
        console.error('Favorite toggle failed', err);
      }
    });
  });

  // Ensure checkout date always stays after checkin date on any date-pair fields
  const checkin = document.getElementById('checkin') || document.getElementById('check_in');
  const checkout = document.getElementById('checkout') || document.getElementById('check_out');
  if (checkin && checkout) {
    checkin.addEventListener('change', function () {
      const nextDay = new Date(this.value);
      nextDay.setDate(nextDay.getDate() + 1);
      const minStr = nextDay.toISOString().split('T')[0];
      checkout.min = minStr;
      if (checkout.value < minStr) checkout.value = minStr;
    });
  }

  // Smooth-scroll for in-page anchor links
  document.querySelectorAll('a[href^="#"]').forEach(function (link) {
    link.addEventListener('click', function (e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });

});
