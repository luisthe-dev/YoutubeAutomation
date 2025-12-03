import sys
import json
import os
import cv2
import numpy as np
import subprocess

def load_image(path):
    # Read image using cv2, handling unicode paths if necessary
    # In Python 3, cv2.imread supports unicode paths on Windows usually, 
    # but sometimes np.fromfile is safer.
    img = cv2.imdecode(np.fromfile(path, dtype=np.uint8), cv2.IMREAD_COLOR)
    return img

def ken_burns_effect(img, duration, fps, width, height):
    # Simple zoom in effect
    frames = []
    num_frames = int(duration * fps)
    
    h, w = img.shape[:2]
    
    # Target aspect ratio
    target_aspect = width / height
    img_aspect = w / h
    
    # Crop to target aspect ratio first
    if img_aspect > target_aspect:
        # Image is wider, crop width
        new_w = int(h * target_aspect)
        start_x = (w - new_w) // 2
        img = img[:, start_x:start_x+new_w]
    else:
        # Image is taller, crop height
        new_h = int(w / target_aspect)
        start_y = (h - new_h) // 2
        img = img[start_y:start_y+new_h, :]
        
    h, w = img.shape[:2]
    
    # Zoom from 1.0 to 1.0
    zoom_start = 1.2
    zoom_end = 1.35
    
    for i in range(num_frames):
        scale = zoom_start + (zoom_end - zoom_start) * (i / num_frames)
        
        # Calculate crop size based on scale
        # We want to crop the center
        crop_w = int(w / scale)
        crop_h = int(h / scale)
        
        x = (w - crop_w) // 2
        y = (h - crop_h) // 2
        
        cropped = img[y:y+crop_h, x:x+crop_w]
        resized = cv2.resize(cropped, (width, height), interpolation=cv2.INTER_LINEAR)
        frames.append(resized)
        
    return frames

def create_video(blueprint_path, output_path, audio_path, thumbnail_path=None):
    with open(blueprint_path, 'r') as f:
        blueprint = json.load(f)
        
    scenes = blueprint.get('scenes', [])
    
    fps = 30
    width = 1920
    height = 1080
    
    temp_video_path = output_path.replace('.mp4', '_temp.mp4')
    
    # Initialize video writer
    fourcc = cv2.VideoWriter_fourcc(*'mp4v')
    out = cv2.VideoWriter(temp_video_path, fourcc, fps, (width, height))
    
    if not out.isOpened():
        print("Error: Could not open video writer.")
        return

    total_frames = 0
    print(f"Processing {len(scenes)} scenes...")
    
    for i, scene in enumerate(scenes):
        print(f"Processing scene {i+1}/{len(scenes)}...")
        image_path = scene.get('image_path')
        duration = scene.get('duration', 5) # Default 5 seconds
        
        if not image_path or not os.path.exists(image_path):
            print(f"Warning: Image not found: {image_path}")
            # Create black frame as fallback
            black_frame = np.zeros((height, width, 3), dtype=np.uint8)
            for _ in range(int(duration * fps)):
                out.write(black_frame)
                total_frames += 1
            continue
            
        img = load_image(image_path)
        if img is None:
            print(f"Warning: Failed to load image: {image_path}")
            continue
            
        frames = ken_burns_effect(img, duration, fps, width, height)
        for frame in frames:
            out.write(frame)
            total_frames += 1
            
    out.release()
    print(f"Video stitching complete. Total frames written: {total_frames}")
    print("Video stitching complete. Adding audio...")
    
    # Add audio using ffmpeg
    if os.path.exists(audio_path):
        # ffmpeg -i temp.mp4 -i audio.mp3 -c:v copy -c:a aac -shortest output.mp4
        cmd = [
            'ffmpeg', '-y',
            '-i', temp_video_path,
            '-i', audio_path
        ]
        
        if thumbnail_path and os.path.exists(thumbnail_path):
            print("Attaching thumbnail...")
            cmd.extend(['-i', thumbnail_path])
            cmd.extend(['-map', '0', '-map', '1', '-map', '2'])
            cmd.extend(['-c:v', 'copy', '-c:a', 'aac'])
            cmd.extend(['-disposition:v:1', 'attached_pic'])
        else:
            cmd.extend(['-c:v', 'copy', '-c:a', 'aac'])
            
        # cmd.extend(['-shortest', output_path])
        cmd.append(output_path)
        
        subprocess.run(cmd, check=True)
        
        # Clean up temp video
        if os.path.exists(temp_video_path):
            os.remove(temp_video_path)
        print(f"Video created successfully: {output_path}")
    else:
        print(f"Warning: Audio not found: {audio_path}")
        os.rename(temp_video_path, output_path)
        print(f"Video created (no audio): {output_path}")

if __name__ == "__main__":
    if len(sys.argv) < 4:
        print("Usage: python render_video.py <blueprint_path> <output_path> <audio_path> [thumbnail_path]")
        sys.exit(1)
        
    blueprint_path = sys.argv[1]
    output_path = sys.argv[2]
    audio_path = sys.argv[3]
    thumbnail_path = sys.argv[4] if len(sys.argv) > 4 else None
    
    create_video(blueprint_path, output_path, audio_path, thumbnail_path)
