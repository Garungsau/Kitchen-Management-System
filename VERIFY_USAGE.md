#!/usr/bin/env python3
"""
Server-side face embedding extractor.
Input: --image /absolute/path/to/image
Output JSON:
  {"status":"success","embedding":[...],"dim":128}
or
  {"status":"error","message":"..."}
"""

import argparse
import json
import os
import sys


def main() -> int:
    parser = argparse.ArgumentParser()
    parser.add_argument("--image", required=True, help="Absolute path to image")
    args = parser.parse_args()

    image_path = os.path.abspath(args.image)
    if not os.path.isfile(image_path):
        print(json.dumps({"status": "error", "message": "Image not found"}))
        return 0

    try:
        import face_recognition  # type: ignore
    except Exception:
        print(json.dumps({
            "status": "error",
            "message": "Missing python library face_recognition. Install: pip install face_recognition"
        }))
        return 0

    try:
        image = face_recognition.load_image_file(image_path)
        encodings = face_recognition.face_encodings(image)
        if not encodings:
            print(json.dumps({"status": "error", "message": "No face detected"}))
            return 0

        emb = encodings[0]
        embedding = [float(x) for x in emb.tolist()]
        print(json.dumps({"status": "success", "embedding": embedding, "dim": len(embedding)}))
        return 0
    except Exception as exc:
        print(json.dumps({"status": "error", "message": str(exc)}))
        return 0


if __name__ == "__main__":
    sys.exit(main())
