from flask import Flask, request, jsonify
from flask_cors import CORS
from predictor import AMDGTPredictor

app = Flask(__name__)
CORS(app)

predictor_b = AMDGTPredictor(dataset="B-dataset")
predictor_c = AMDGTPredictor(dataset="C-dataset")
predictor_f = AMDGTPredictor(dataset="F-dataset")

PREDICTORS = {
    "B-dataset": predictor_b,
    "C-dataset": predictor_c,
    "F-dataset": predictor_f,
}


def get_predictor(dataset_name):
    if dataset_name not in PREDICTORS:
        dataset_name = "B-dataset"
    return PREDICTORS[dataset_name]


@app.route("/")
def home():
    return jsonify({
        "message": "AMDGT ORIGINAL API running",
        "model": "original"
    })


@app.route("/predict", methods=["POST"])
def predict():
    try:
        data = request.get_json() or {}

        input_type = str(data.get("input_type", "")).strip()
        keyword = str(data.get("keyword", "")).strip()
        dataset_name = str(data.get("dataset", "B-dataset")).strip()
        top_k = int(data.get("top_k", 5))

        if dataset_name not in PREDICTORS:
            dataset_name = "B-dataset"

        if input_type not in ["drug", "disease"]:
            return jsonify({
                "success": False,
                "message": "Model gốc chỉ hỗ trợ drug hoặc disease"
            }), 400

        if not keyword:
            return jsonify({
                "success": False,
                "message": "Thiếu từ khóa"
            }), 400

        if top_k <= 0:
            top_k = 5
        if top_k > 10:
            top_k = 10

        predictor = get_predictor(dataset_name)

        results = predictor.predict(
            input_type=input_type,
            keyword=keyword,
            top_k=top_k
        )

        graph = predictor.explain_prediction_graph(
            input_type=input_type,
            keyword=keyword,
            top_k=min(top_k, 5),
            protein_k=5
        )

        return jsonify({
            "success": True,
            "model_type": "original",
            "dataset": dataset_name,
            "input_type": input_type,
            "keyword": keyword,
            "results": results,
            "graph": graph
        })

    except Exception as e:
        return jsonify({
            "success": False,
            "message": str(e)
        }), 500


if __name__ == "__main__":
    app.run(host="127.0.0.1", port=5001, debug=False)