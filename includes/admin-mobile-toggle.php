<script>
// Mobile sidebar toggle — shared across all admin pages
document.addEventListener('DOMContentLoaded', function() {
  const toggle = document.querySelector('.dash-mobile-toggle');
  const sidebar = document.getElementById('dashSidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', () => sidebar.classList.toggle('open'));
  }
});
</script>
