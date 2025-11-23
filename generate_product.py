import json
from pathlib import Path


def slugify_color(color: str) -> str:
    return color.upper().replace(" ", "").replace("-", "").replace("/", "")


sizes = [
    {"name": "S", "weight": 0.6},
    {"name": "M", "weight": 0.65},
    {"name": "L", "weight": 0.7},
    {"name": "XL", "weight": 0.75},
    {"name": "2XL", "weight": 0.8},
    {"name": "3XL", "weight": 0.85},
]

colors = [
    "Navy",
    "Orange",
    "Light Pink",
    "Red",
    "Purple",
    "Cardinal",
    "Maroon",
    "Kelly Green",
    "Royal",
    "White",
    "Light Blue",
    "Vegas Gold",
    "Black",
    "Crimson",
    "Powder Blue",
    "Dark Green",
    "Dark Grey",
    "Light Grey",
]

color_images = [
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/4_ac9e0eb9-87e3-40fe-89c6-1d31a214302a.png?v=1763879408",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/1_72c4ba0f-94c9-4459-a9d6-2d41307dc569.png?v=1763879408",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/2_6873152b-943d-41fc-ba23-e257fb3ea891.png?v=1763879408",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/18.png?v=1763879408",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/7_e3ed84fa-7476-426c-9357-96fe06e97a1d.png?v=1763879407",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/9_e81a85da-6452-4f25-b10f-95ec22a8f617.png?v=1763879408",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/14.png?v=1763879408",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/5_f3a9d1a3-c7a2-4302-a102-710f1ab5d97c.png?v=1763879407",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/10_8d0cb500-32f3-451e-ab06-e73d2771852d.png?v=1763879407",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/17.png?v=1763879408",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/15.png?v=1763879408",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/6_2fd75632-e732-4855-9bd9-02411f0ee68f.png?v=1763879407",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/12.png?v=1763879408",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/11.png?v=1763879408",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/8_b1e5b0ce-fb23-4fab-bb4b-d75422eb4937.png?v=1763879407",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/13.png?v=1763879407",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/3_aa5a3833-b952-4f1f-81e2-d682532949bf.png?v=1763879407",
    "https://cdn.shopify.com/s/files/1/0675/2449/3544/files/16.png?v=1763879407",
]

color_to_image = dict(zip(colors, color_images))

product = {
    "product": {
        "title": "Product with multiple options and multiple variants upto 100 Plus",
        "body_html": "<p>Premium fleece hoodie with front player graphic.</p>",
        "vendor": "NIL Club",
        "product_type": "Hoodie",
        "status": "active",
        "tags": ["hoodie", "player", "apparel"],
        "options": [
            {"name": "Size", "position": 1, "values": [s["name"] for s in sizes]},
            {"name": "Color", "position": 2, "values": colors},
        ],
        "images": [
            {
                "position": idx + 1,
                "src": color_to_image[color],
                "alt": f"{color} hoodie front",
            }
            for idx, color in enumerate(colors)
        ],
        "variants": [],
    }
}

variants = []
for size in sizes:
    for color in colors:
        variant_title = f"{size['name']} / {color}"
        variants.append(
            {
                "option1": size["name"],
                "option2": color,
                "sku": f"HD-PLAYER-{slugify_color(color)}-{size['name']}",
                "title": variant_title,
                "price": "59.99",
                "compare_at_price": "69.99",
                "taxable": True,
                "requires_shipping": True,
                "weight": size["weight"],
                "weight_unit": "kg",
                "image": {
                    "src": color_to_image[color],
                    "alt": variant_title,
                },
            }
        )

product["product"]["variants"] = variants

output_path = Path("product.json")
output_path.write_text(json.dumps(product, indent=2) + "\n", encoding="utf-8")

print(f"Wrote {output_path} with {len(variants)} variants and {len(colors)} images.")
