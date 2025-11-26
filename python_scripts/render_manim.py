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

def render_scene(blueprint_path):
    with open(blueprint_path, 'r') as f:
        blueprint = json.load(f)

    # Basic error checking
    if 'scenes' not in blueprint:
        print("Error: No 'scenes' key in blueprint.")
        sys.exit(1)

    # Create a dynamic scene class
    class GeneratedScene(MovingCameraScene):
        def construct(self):
            # Zoom out slightly to fit more content
            self.camera.frame.scale(1.3)
            
            title_text = blueprint.get('title', 'Untitled')
            title = Text(title_text).scale(1.5)
            self.play(Write(title))
            self.wait(1)
            self.play(FadeOut(title))

            # Use a persistent context for variables to be shared across snippets
            execution_context = {'self': self}
            
            for i, scene_data in enumerate(blueprint['scenes']):
                print(f"--- Processing Scene {i+1} ---")
                
                # Clear previous scene elements
                self.clear()
                # Re-apply camera zoom if clear() resets it (it usually doesn't reset camera, but good to be safe or just rely on persistent camera)
                # self.camera.frame.scale(1.2) # clear() does not reset camera in Manim.

                # 1. Handle Audio
                audio_path = scene_data.get('audio_path')
                audio_duration = 0
                if audio_path and os.path.exists(audio_path):
                    print(f"Adding audio: {audio_path}")
                    self.add_sound(audio_path)
                    audio_duration = get_audio_duration(audio_path)
                    print(f"Audio duration: {audio_duration}s")
                
                # 2. Handle Image / Ken Burns Effect
                image_path = scene_data.get('image_path')
                code = scene_data.get('manim_code')
                
                duration = audio_duration if audio_duration > 0 else 5.0
                
                if image_path and os.path.exists(image_path):
                    print(f"Applying Ken Burns effect to: {image_path}")
                    
                    # Create ImageMobject
                    image = ImageMobject(image_path)
                    
                    # Scale to cover the screen (preserve aspect ratio)
                    # Ensure it covers the frame completely
                    scale_factor = max(config.frame_width / image.width, config.frame_height / image.height)
                    # Scale up slightly more (e.g. 1.2x) to allow room for panning/zooming without showing edges
                    image.scale(scale_factor * 1.2)
                    
                    # Center the image
                    image.move_to(ORIGIN)
                    
                    # Randomize Ken Burns Effect
                    # Options: Zoom In, Zoom Out, Pan (Left/Right/Up/Down)
                    import random
                    effect_type = random.choice(['zoom_in', 'zoom_out', 'pan'])
                    
                    self.add(image)
                    
                    if effect_type == 'zoom_in':
                        # Start normal, zoom in
                        self.play(image.animate.scale(1.15), run_time=duration, rate_func=linear)
                    elif effect_type == 'zoom_out':
                        # Start zoomed in, zoom out
                        image.scale(1.15)
                        self.play(image.animate.scale(1/1.15), run_time=duration, rate_func=linear)
                    else: # Pan
                        # Start slightly off-center, move to opposite
                        direction = random.choice([UP, DOWN, LEFT, RIGHT])
                        start_shift = direction * 0.5
                        end_shift = -direction * 0.5
                        
                        image.shift(start_shift)
                        self.play(image.animate.shift(end_shift - start_shift), run_time=duration, rate_func=linear)
                        
                # 3. Execute Manim Code (Fallback)
                elif code:
                    try:
                        # Print code safely
                        print(f"Code: {code[:100]}..." if len(code) > 100 else f"Code: {code}")
                        
                        start_time = self.renderer.time
                        exec(code, globals(), execution_context)
                        end_time = self.renderer.time
                        elapsed = end_time - start_time
                        
                        if audio_duration > elapsed:
                            wait_time = audio_duration - elapsed
                            print(f"Waiting {wait_time:.2f}s to sync with audio.")
                            self.wait(wait_time)
                            
                    except Exception as e:
                        print(f"FAILURE in Scene {i+1}: {e}")
                else:
                    # If no code and no image, just wait for audio
                    if audio_duration > 0:
                        self.wait(audio_duration)

    # Render the scene
    # Manim config
    config.media_dir = os.path.dirname(blueprint_path) # Output where the blueprint is
    config.verbosity = "WARNING"
    
    scene = GeneratedScene()
    scene.render()

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python render_manim.py <path_to_blueprint.json>")
        sys.exit(1)
    
    render_scene(sys.argv[1])
