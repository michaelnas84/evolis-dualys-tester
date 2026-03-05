from flask import Flask, request, jsonify
from flask_cors import CORS
from rembg import remove, new_session
from PIL import Image
from io import BytesIO
import base64
import os

app = Flask(__name__)
CORS(app)

session = new_session()

default_background_path = os.environ.get(
    "DEFAULT_BACKGROUND_PATH",
    r"C:\card_hotfolder\backgrounds\default.jpg"
)

def fitBackgroundToSize(background_image, target_size):
    # Resize/crop like CSS background-size: cover
    target_width, target_height = target_size
    background_width, background_height = background_image.size

    scale = max(target_width / background_width, target_height / background_height)
    resized_width = int(background_width * scale)
    resized_height = int(background_height * scale)

    resized = background_image.resize((resized_width, resized_height), Image.LANCZOS)

    left = (resized_width - target_width) // 2
    top = (resized_height - target_height) // 2

    return resized.crop((left, top, left + target_width, top + target_height))

def dataUrlToBytes(data_url):
    header, encoded = data_url.split(",", 1)
    return base64.b64decode(encoded)

@app.route("/remove-bg", methods=["POST"])
def removeBg():
    try:
        data = request.json or {}
        image_data_url = data.get("image")
        background_data_url = data.get("background")  # optional data:image/... base64
        apply_background = bool(data.get("apply_background")) or (background_data_url is not None)

        if not image_data_url:
            return jsonify({"ok": False, "error": "Missing 'image' in request body"}), 400

        image_bytes = dataUrlToBytes(image_data_url)

        cutout_bytes = remove(image_bytes, session=session)
        cutout_image = Image.open(BytesIO(cutout_bytes)).convert("RGBA")

        output_bytes = cutout_bytes
        output_mime = "image/png"

        if apply_background:
            if background_data_url:
                background_bytes = dataUrlToBytes(background_data_url)
                background_image = Image.open(BytesIO(background_bytes)).convert("RGBA")
            else:
                if not os.path.exists(default_background_path):
                    return jsonify({"ok": False, "error": f"Background not found: {default_background_path}"}), 500
                background_image = Image.open(default_background_path).convert("RGBA")

            background_image = fitBackgroundToSize(background_image, cutout_image.size)
            composed_image = Image.alpha_composite(background_image, cutout_image)

            output_format = (data.get("output_format") or "png").lower()
            output_buffer = BytesIO()

            if output_format in ["jpg", "jpeg"]:
                composed_image.convert("RGB").save(output_buffer, format="JPEG", quality=95)
                output_mime = "image/jpeg"
            else:
                composed_image.save(output_buffer, format="PNG")
                output_mime = "image/png"

            output_bytes = output_buffer.getvalue()

        result_base64 = base64.b64encode(output_bytes).decode("utf-8")

        return jsonify({
            "ok": True,
            "image": f"data:{output_mime};base64," + result_base64
        })

    except Exception as e:
        return jsonify({"ok": False, "error": str(e)}), 500

if __name__ == "__main__":
    app.run(port=5001)