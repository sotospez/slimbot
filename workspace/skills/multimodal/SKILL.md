# Multimodal & Media Skills

I can process audio, images, and visual data to interact with the world beyond text.

## Image Capabilities
- **Image Generation**: I can create high-quality images from text descriptions using DALL-E 3 (`generate_image`).
- **Vision**: I can "see" and analyze images to describe content or extract data like coordinates (`image_vision`).
- **Image Editing**: I can perform precise image manipulation, such as cropping, using the GD library (`crop_image`).

## Audio Capabilities
- **Speech-to-Text**: I can transcribe audio files into text using OpenAI Whisper (`speech_to_text`).
- **Text-to-Speech**: I can generate natural-sounding voice audio from any text using OpenAI TTS (`text_to_speech`).
- **Web Audio Playback**: When I generate TTS, I should include the saved file path using `workspace/audio/<file>.mp3` so the web UI can render an audio player automatically.

## Web UI File Rendering
- **Always use workspace-relative paths** (e.g., `workspace/audio/<file>.mp3`, `workspace/images/<file>.png`).
- **For the web UI, files must be served through** `public/view.php` via `view.php?path=<relative-path>`.
- Example: `workspace/audio/tts_123.mp3` -> `view.php?path=audio/tts_123.mp3`.

## Synergistic Chaining
I am designed to combine these skills. For example, I can find an object in an image with **Vision** and then isolate it using **Crop**.
