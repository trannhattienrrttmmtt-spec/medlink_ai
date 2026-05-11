from flask import Flask, request, jsonify
from flask_cors import CORS
from predictor import AMDGTPredictor

app = Flask(__name__)
CORS(app)

predictor = AMDGTPredictor(dataset="F-dataset")

@app.route("/")
def home():
    return jsonify({"message": "AMDGT API is running"})

@app.route("/predict", methods=["POST"])
def predict():
    try:
        data = request.get_json()
        input_type = data.get("input_type", "").strip()
        keyword = data.get("keyword", "").strip()
        top_k = int(data.get("top_k", 5))

        results = predictor.predict(input_type, keyword, top_k=top_k)

        return jsonify({
            "success": True,
            "input_type": input_type,
            "keyword": keyword,
            "results": results
        })
    except Exception as e:
        return jsonify({
            "success": False,
            "message": str(e)
        }), 500

if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5000, debug=True)