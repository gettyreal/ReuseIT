// Lightweight test to verify profile functionality
console.log('=== Profile Page Test ===');

// Check if functions exist
const funcsToCheck = [
  'showProfile',
  'loadUserProfile',
  'renderAvatar',
  'formatDate'
];

funcsToCheck.forEach(func => {
  if (typeof window[func] === 'function') {
    console.log(`✓ ${func} function exists`);
  } else {
    console.log(`✗ ${func} function missing`);
  }
});

// Check CSS classes exist
const cssClasses = [
  '.profile-header',
  '.profile-stats-grid',
  '.stat-card',
  '.avatar-image',
  '.avatar-initials'
];

const styleSheets = document.styleSheets;
cssClasses.forEach(cls => {
  let found = false;
  for (let sheet of styleSheets) {
    try {
      if (sheet.cssRules) {
        for (let rule of sheet.cssRules) {
          if (rule.selectorText === cls) {
            found = true;
            break;
          }
        }
      }
    } catch (e) {}
  }
  console.log(`${found ? '✓' : '✗'} ${cls} CSS class exists`);
});

console.log('=== Test Complete ===');
