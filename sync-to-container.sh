#!/bin/bash
# Quick sync script to copy changes to running container

CONTAINER="leantime-leantime-1"
LOCAL_DIR="$HOME/leantime-sb"

echo "🔄 Syncing changes to container..."

# Check if container is running
if ! docker ps | grep -q $CONTAINER; then
    echo "❌ Container $CONTAINER is not running!"
    exit 1
fi

# Sync specific file or directory
if [ -n "$1" ]; then
    FILE_PATH="$1"
    
    # Remove leading ./ or /
    FILE_PATH="${FILE_PATH#./}"
    FILE_PATH="${FILE_PATH#/}"
    
    if [ -f "$LOCAL_DIR/$FILE_PATH" ]; then
        echo "📄 Copying $FILE_PATH..."
        docker cp "$LOCAL_DIR/$FILE_PATH" "$CONTAINER:/var/www/html/$FILE_PATH"
        echo "✅ File synced!"
    elif [ -d "$LOCAL_DIR/$FILE_PATH" ]; then
        echo "📁 Copying directory $FILE_PATH..."
        docker cp "$LOCAL_DIR/$FILE_PATH" "$CONTAINER:/var/www/html/$(dirname $FILE_PATH)/"
        echo "✅ Directory synced!"
    else
        echo "❌ File or directory not found: $FILE_PATH"
        exit 1
    fi
else
    # Sync common modified files
    echo "📄 Syncing modified files..."
    
    # Calendar widget
    docker cp "$LOCAL_DIR/app/Domain/Widgets/Templates/partials/calendar.blade.php" \
        "$CONTAINER:/var/www/html/app/Domain/Widgets/Templates/partials/calendar.blade.php"
    
    # Calendar JS
    docker cp "$LOCAL_DIR/app/Domain/Calendar/Js/calendarController.js" \
        "$CONTAINER:/var/www/html/app/Domain/Calendar/Js/calendarController.js"
    
    # Built assets (if they exist)
    if [ -d "$LOCAL_DIR/public/dist" ]; then
        echo "📦 Syncing frontend assets..."
        docker cp "$LOCAL_DIR/public/dist/" "$CONTAINER:/var/www/html/public/"
    fi
    
    echo "✅ All files synced!"
fi

echo ""
echo "💡 Usage:"
echo "  ./sync-to-container.sh                    # Sync all modified files"
echo "  ./sync-to-container.sh path/to/file.php   # Sync specific file"
echo ""
echo "🌐 Refresh your browser to see changes!"
