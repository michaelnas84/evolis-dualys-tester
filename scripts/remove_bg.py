from flask import Flask, request, jsonify
from flask_cors import CORS
from rembg import remove
from rembg import new_session
session = new_session()
import base64

app = Flask(__name__)
CORS(app)

@app.route('/remove-bg', methods=['POST'])
def remove_bg():

    try:
        data = request.json
        image_data = data["image"]

        # remove prefixo data:image
        header, encoded = image_data.split(",", 1)

        image_bytes = base64.b64decode(encoded)

        result = remove(image_bytes, session=session)

        result_base64 = base64.b64encode(result).decode("utf-8")

        return jsonify({
            "ok": True,
            "image": "data:image/png;base64," + result_base64
        })

    except Exception as e:
        return jsonify({
            "ok": False,
            "error": str(e)
        })

if __name__ == "__main__":
    app.run(port=5001)