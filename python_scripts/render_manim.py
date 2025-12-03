import sys
import json
import os
import subprocess
from manim import *

# Monkeypatch Tex and MathTex to use Text since LaTeX is not available
Tex = Text
MathTex = Text

def get_audio_duration(file_path):
    """Get the duration of an audio file using ffprobe."""
    try:
        cmd = [
            "ffprobe", 
            "-v", "error", 
            "-show_entries", "format=duration", 
            "-of", "default=noprint_wrappers=1:nokey=1", 
            file_path
        ]
        result = subprocess.run(cmd, stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)
        return float(result.stdout.strip())
    except Exception as e:
        print(f"Warning: Could not determine audio duration for {file_path}: {e}")
        return 0

def render_scene(blueprint_path, output_path, audio_path, thumbnail_path=None):
    with open(blueprint_path, 'r') as f:
        blueprint = json.load(f)

    # Basic error checking
    if 'scenes' not in blueprint:
        print("Error: No 'scenes' key in blueprint.")
        sys.exit(1)

    # Manim config
    config.media_dir = os.path.dirname(blueprint_path) # Output where the blueprint is
    config.verbosity = "WARNING"
    # Set higher quality if needed, or default
    config.pixel_height = 1080
    config.pixel_width = 1920
    config.frame_rate = 30

    # Create a dynamic scene class
    class GeneratedScene(MovingCameraScene):
        def construct(self):
            # Zoom out slightly to fit more content
            self.camera.frame.scale(1)
            
            # Optional title - REMOVED per user request
            # if 'title' in blueprint:
            #     title_text = blueprint.get('title', 'Untitled')
            #     title = Text(title_text).scale(1.5)
            #     self.play(Write(title))
            #     self.wait(1)
            #     self.play(FadeOut(title))

            # Use a persistent context for variables to be shared across snippets
            execution_context = {'self': self}
            
            print(f"Processing {len(blueprint['scenes'])} scenes...")

            for i, scene_data in enumerate(blueprint['scenes']):
                print(f"--- Processing Scene {i+1}/{len(blueprint['scenes'])} ---")
                
                # Clear previous scene elements? 
                # Manim keeps elements unless removed. 
                # For a sequence of distinct scenes, we usually want to clear or transition.
                self.clear()
                
                # 1. Handle Image / Ken Burns Effect
                image_path = scene_data.get('image_path')
                code = scene_data.get('manim_code')
                duration = float(scene_data.get('duration', 5.0))
                
                if image_path and os.path.exists(image_path):
                    print(f"Applying Ken Burns effect to: {image_path}")
                    
                    # Create ImageMobject
                    image = ImageMobject(image_path)
                    
                    # Scale to cover the screen (preserve aspect ratio)
                    scale_factor = max(config.frame_width / image.width, config.frame_height / image.height)
                    image.scale(scale_factor * 1.15)
                    
                    # Center the image
                    image.move_to(ORIGIN)
                    
                    # Randomize Ken Burns Effect
                    import random
                    effect_type = random.choice(['zoom_in', 'zoom_out', 'pan'])
                    
                    self.add(image)
                    
                    if effect_type == 'zoom_in':
                        self.play(image.animate.scale(1.2), run_time=duration, rate_func=linear)
                    elif effect_type == 'zoom_out':
                        image.scale(1.2)
                        self.play(image.animate.scale(1/1.15), run_time=duration, rate_func=linear)
                    else: # Pan
                        direction = random.choice([UP, DOWN, LEFT, RIGHT])
                        start_shift = direction * 0.5
                        end_shift = -direction * 0.5
                        
                        image.shift(start_shift)
                        self.play(image.animate.shift(end_shift - start_shift), run_time=duration, rate_func=linear)
                        
                # 2. Execute Manim Code (Fallback)
                elif code:
                    try:
                        print(f"Executing Manim code...")
                        exec(code, globals(), execution_context)
                        # Note: The code itself should handle timing/wait. 
                        # If it doesn't, we might fall short or go long.
                        # We can enforce a minimum wait if needed.
                    except Exception as e:
                        print(f"FAILURE in Scene {i+1}: {e}")
                        # Fallback wait
                        self.wait(duration)
                else:
                    # If no code and no image, just wait
                    self.wait(duration)

    scene = GeneratedScene()
    scene.render()
    
    # Locate the generated video file
    # Manim saves to: <media_dir>/videos/<quality>/GeneratedScene.mp4
    # We need to find it.
    
    # Construct expected path based on config
    quality_dir = f"{config.pixel_height}p{config.frame_rate}"
    manim_output = os.path.join(config.media_dir, "videos", quality_dir, "GeneratedScene.mp4")
    
    if not os.path.exists(manim_output):
        # Try default 1080p60 or similar if config didn't take
        # Search recursively in media_dir for GeneratedScene.mp4
        found = False
        for root, dirs, files in os.walk(os.path.join(config.media_dir, "videos")):
            if "GeneratedScene.mp4" in files:
                manim_output = os.path.join(root, "GeneratedScene.mp4")
                found = True
                break
        if not found:
            print("Error: Could not locate Manim output file.")
            sys.exit(1)

    print(f"Manim render complete: {manim_output}")
    print("Merging audio and attaching thumbnail...")

    # Merge with Audio and Thumbnail using FFmpeg
    if os.path.exists(audio_path):
        cmd = [
            'ffmpeg', '-y',
            '-i', manim_output,
            '-i', audio_path
        ]

        cmd.extend(['-shortest', output_path]) 
        
        # if thumbnail_path and os.path.exists(thumbnail_path):
        #     print(f"Attaching thumbnail from: {thumbnail_path}")
        #     cmd.extend(['-i', thumbnail_path])
        #     # Explicit mapping: Video from 0, Audio from 1, Thumbnail (Video) from 2
        #     cmd.extend(['-map', '0:v', '-map', '1:a', '-map', '2:v'])
        #     cmd.extend(['-c:v', 'copy', '-c:a', 'aac'])
        #     # Set disposition for the thumbnail stream (which is the second video stream, v:1)
        #     cmd.extend(['-disposition:v:1', 'attached_pic'])
        # else:
            cmd.extend(['-c:v', 'copy', '-c:a', 'aac'])
        
        cmd.append(output_path)

        print(f"Running FFmpeg command: {' '.join(cmd)}")
        subprocess.run(cmd, check=True)
        print(f"Final video created: {output_path}")
        
    else:
        print(f"Warning: Audio not found: {audio_path}")
        # Just move/copy the video
        import shutil
        shutil.copy(manim_output, output_path)
        print(f"Video created (no audio): {output_path}")

if __name__ == "__main__":
    if len(sys.argv) < 4:
        print("Usage: python render_manim.py <blueprint_path> <output_path> <audio_path> [thumbnail_path]")
        sys.exit(1)
    
    blueprint_path = sys.argv[1]
    output_path = sys.argv[2]
    audio_path = sys.argv[3]
    thumbnail_path = sys.argv[4] if len(sys.argv) > 4 else None
    
    render_scene(blueprint_path, output_path, audio_path, thumbnail_path)
