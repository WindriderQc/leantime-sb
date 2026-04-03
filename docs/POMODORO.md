# Pomodoro Timer Integration

## Overview

The Pomodoro Timer feature provides time management functionality using the Pomodoro Technique - work in focused 25-minute sessions with short breaks in between.

**Source:** Integrated from Naros/leantime fork  
**Location:** `public/assets/js/libs/pomodoro/`  
**License:** MIT

## Features

- ⏱️ Countdown timer with start/pause/reset controls
- 🎯 Preset durations (25min work, 5min short break, 15min long break)
- 🔊 Audio notifications when timer completes
- 📊 Session tracking
- 🎨 Customizable appearance
- 📱 Responsive design
- 🌙 Dark mode support

## Files

```
public/assets/js/libs/pomodoro/
├── pomodoro.js              # Core timer functionality
├── pomodoro.html            # Standalone HTML demo
├── pomodoro.css             # Original styles
├── pomodoro-enhanced.css    # Enhanced Leantime-integrated styles
├── LICENSE                  # MIT License
├── README.md               # Original documentation
├── audio/                  # Sound files for notifications
└── images/                 # Timer icons
```

## Usage

### Basic Implementation

Include the JavaScript and CSS in your template:

```html
<!-- Include Pomodoro CSS -->
<link rel="stylesheet" href="<?=BASE_URL?>/assets/js/libs/pomodoro/pomodoro-enhanced.css">

<!-- Include Pomodoro JS -->
<script src="<?=BASE_URL?>/assets/js/libs/pomodoro/pomodoro.js"></script>
```

### Initialize Timer

```javascript
// Create timer container
<div id="pomodoro-timer"></div>

// Initialize widget
const pomodoro = new PomodoroWidget('pomodoro-timer', {
    workDuration: 25 * 60,      // 25 minutes
    shortBreak: 5 * 60,         // 5 minutes
    longBreak: 15 * 60,         // 15 minutes
    sound: true,                // Enable sound notifications
    notifications: true,        // Enable browser notifications
    autoStartBreak: false,      // Auto-start break after work
    autoStartWork: false,       // Auto-start work after break
    onWorkComplete: function(sessions) {
        console.log('Work session complete! Total sessions:', sessions);
    },
    onBreakComplete: function() {
        console.log('Break complete!');
    }
});
```

### Simple Countdown Timer

For custom durations without Pomodoro workflow:

```javascript
// Create a 10-minute timer
const timer = new CountdownTimer(
    10 * 60,  // Duration in seconds
    function onTick(remaining) {
        // Update display each second
        document.getElementById('timer-display').textContent = 
            CountdownTimer.formatTime(remaining);
    },
    function onComplete() {
        // Timer finished
        alert('Time is up!');
    }
);

// Start the timer
timer.start();

// Pause the timer
timer.pause();

// Reset the timer
timer.reset();
```

## Integration with Leantime

### Dashboard Widget

Add Pomodoro timer to the dashboard:

```php
// app/Domain/Dashboard/Templates/dashboard.blade.php

<div class="widget pomodoro-widget-container">
    <div class="widget-header">
        <h3>Pomodoro Timer</h3>
    </div>
    <div class="widget-body">
        <div id="dashboard-pomodoro"></div>
    </div>
</div>

<script>
leantime.ready(function() {
    new PomodoroWidget('dashboard-pomodoro', {
        sound: true,
        notifications: true
    });
});
</script>
```

### Task Integration

Track time spent on tasks:

```javascript
// Start Pomodoro when working on a task
const taskPomodoro = new PomodoroWidget('task-timer', {
    workDuration: 25 * 60,
    onWorkComplete: function(sessions) {
        // Log time to task
        leantime.tasksController.logTime({
            taskId: currentTaskId,
            duration: 25,  // minutes
            description: 'Pomodoro session ' + sessions
        });
    }
});
```

### Calendar Integration

Add timer to calendar view:

```php
// app/Domain/Calendar/Templates/showMyCalendar.blade.php

<div class="calendar-sidebar">
    <div id="calendar-pomodoro"></div>
</div>

<script>
leantime.ready(function() {
    new PomodoroWidget('calendar-pomodoro');
});
</script>
```

## API Reference

### CountdownTimer Class

**Constructor:**
```javascript
new CountdownTimer(duration, onTick, onComplete)
```

**Methods:**
- `start()` - Start or resume the timer
- `pause()` - Pause the timer
- `stop()` - Stop and clear the timer
- `reset()` - Reset to original duration
- `setDuration(seconds)` - Set new duration
- `getRemaining()` - Get remaining seconds
- `isActive()` - Check if timer is running

**Static Methods:**
- `CountdownTimer.formatTime(seconds)` - Format seconds to MM:SS

### PomodoroWidget Class

**Constructor:**
```javascript
new PomodoroWidget(containerId, options)
```

**Options:**
| Option | Type | Default | Description |
|--------|------|---------|-------------|
| workDuration | number | 1500 | Work session duration (seconds) |
| shortBreak | number | 300 | Short break duration (seconds) |
| longBreak | number | 900 | Long break duration (seconds) |
| autoStartBreak | boolean | false | Auto-start break after work |
| autoStartWork | boolean | false | Auto-start work after break |
| sound | boolean | true | Enable sound notifications |
| notifications | boolean | false | Enable browser notifications |
| onWorkComplete | function | null | Callback when work session ends |
| onBreakComplete | function | null | Callback when break ends |

**Methods:**
- `start()` - Start the timer
- `pause()` - Pause the timer
- `reset()` - Reset the timer
- `setDuration(seconds)` - Set custom duration
- `startWork()` - Start work session
- `startBreak(duration)` - Start break session
- `destroy()` - Clean up and remove widget

## Customization

### Custom Styling

Override CSS variables:

```css
.pomodoro-widget {
    --timer-color: #ea5b0c;
    --timer-bg: #ffffff;
    --timer-border: #e0e0e0;
}
```

### Custom Sound

Replace the default sound:

```javascript
PomodoroWidget.prototype.playCompletionSound = function() {
    const audio = new Audio('/path/to/custom-sound.mp3');
    audio.play();
};
```

### Keyboard Shortcuts

Add keyboard controls:

```javascript
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.key === 'p') {
        e.preventDefault();
        pomodoro.start();
    }
    if (e.ctrlKey && e.key === 'b') {
        e.preventDefault();
        pomodoro.pause();
    }
});
```

## Browser Notifications

Request notification permission:

```javascript
if ('Notification' in window && Notification.permission === 'default') {
    Notification.requestPermission().then(permission => {
        if (permission === 'granted') {
            console.log('Notifications enabled');
        }
    });
}
```

## Testing

Test the timer functionality:

```javascript
// Create test timer (5 seconds)
const testTimer = new CountdownTimer(5, 
    (remaining) => console.log('Remaining:', remaining),
    () => console.log('Complete!')
);

testTimer.start();
```

## Troubleshooting

**Timer not starting:**
- Check if container element exists
- Verify JavaScript is loaded after DOM
- Check browser console for errors

**Sound not playing:**
- Check browser autoplay policy
- User interaction may be required before audio
- Verify Web Audio API support

**Notifications not showing:**
- Request notification permission
- Check browser notification settings
- Ensure HTTPS (required for notifications)

## Future Enhancements

Planned improvements:

1. **Statistics Dashboard** - Track productivity over time
2. **Goal Setting** - Set daily Pomodoro goals
3. **Task Linking** - Auto-track time to specific tasks
4. **Team Pomodoros** - Synchronized team work sessions
5. **Custom Intervals** - User-defined work/break durations
6. **Mobile App** - Native mobile timer

## Resources

- **Pomodoro Technique:** https://francescocirillo.com/pages/pomodoro-technique
- **Original Fork:** https://github.com/Naros/leantime
- **License:** MIT (see LICENSE file)

## Contributing

To improve the Pomodoro timer:

1. Edit files in `public/assets/js/libs/pomodoro/`
2. Test changes locally
3. Sync to container: `./sync-to-container.sh`
4. Commit with `[POMODORO]` prefix
