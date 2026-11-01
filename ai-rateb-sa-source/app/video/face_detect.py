"""Print detected frontal faces as JSON. Exit 2 when OpenCV or the cascade is missing."""
import json
import os
import sys

def main() -> int:
    if len(sys.argv) < 3:
        return 2
    try:
        import cv2
    except ImportError:
        return 2
    cascade = cv2.CascadeClassifier(sys.argv[1])
    if cascade.empty():
        return 2
    if sys.argv[2] == "--probe":
        return 0

    def faces_in(path: str):
        image = cv2.imread(path)
        if image is None:
            return None
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
        found = cascade.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=5, minSize=(24, 24))
        return [{"x": int(x), "y": int(y), "w": int(w), "h": int(h)} for (x, y, w, h) in found]

    if os.path.isdir(sys.argv[2]):
        names = sorted(name for name in os.listdir(sys.argv[2]) if name.startswith("frame-") and name.endswith(".jpg"))
        frames = []
        for name in names:
            faces = faces_in(os.path.join(sys.argv[2], name))
            if faces is None:
                return 3
            frames.append({"file": name, "faces": faces})
        json.dump({"frames": frames}, sys.stdout)
        return 0
    faces = faces_in(sys.argv[2])
    if faces is None:
        return 3
    json.dump({"faces": faces}, sys.stdout)
    return 0

if __name__ == "__main__":
    sys.exit(main())
