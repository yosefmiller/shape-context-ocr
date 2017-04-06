# Shape Context - Optical Character Recognition (OCR)
The Shape Context is a shape descriptor that captures the relative positions of other points on the shape contours. This gives a globally discriminative characterization of the shape and not just a localized descriptor. These are then used to measure similarities between shapes and to recognize a character based on each edge’s polar-log distance to the other edges.

---
## How to Use
Add the *Matlab OCR* folder to Matlab's path.
Modify the default settings in `Data/OcrDefaults.m`.
To run the program with a GUI, execute `OcrProgram` in the Matlab command window.
To run the program with default settings, execute `ocr` in the Matlab command window.
To train the program with new data, execute `TemplateTraining` in the Matlab command window.
Remember to specify a name for the training data file by editing the `OcrDefaults` settings.

---
### Objective
To explore the current approaches in OCR algorithms by performing a literature survey and then writing my own code using a selected technique. I ultimately chose to implement a Shape Context method to identify characters based on their relative shapes.

---
### Introduction
Suppose you have handwritten notes that you would like to digitize. Imagine an algorithm designed to compare each handwritten character pixel-by-pixel to an identical printed character. Such an algorithm would perform poorly since the shapes are mismatched. To the human observer, however, it is fairly easy to read. Why?

The human brain identifies characters based on its _relative shape_. The mind accounts for distortions, scaling, and other stylistic differences to associate two character sets. For an **Optical Character Recognition** (OCR) algorithm to accurately read such visual data, it must be able to do the same.*

This is the purpose of the highly discriminative **Shape Context** descriptor; it describes the coarse distribution of the rest of the shape with respect to the given point on the shape. The relationship is determined by the logarithmic distance and polar angle between two given contours. These values are classified into an appropriate bin within a 12x5 matrix, forming what is called a histogram. An optimal assignment matching algorithm locates correspondences by maximizing Shape Context similarities. Given the set of correspondences, an aligning transformation is estimated that maps one shape onto the other. The dissimilarity between the two shapes is a sum of the matching errors and magnitude of the aligning transform. Lastly, a nearest-neighbor technique is used to select the prototype with the least dissimilarity.

---
### Results
I trained my algorithm with only 400 handwritten samples, compared to the traditional 60,000 found in research papers. Even with the limited dataset, my final algorithm performed reasonably well. My program could be improved by using the complex Thin Plate Spline transformation in place of the Affine transformation to optimally align similar shapes.

---
### Methodology
Step 1: **Canny Edge Detection**
 1. Apply Gaussian filter to smooth the image in order to remove the noise  
 2. Find the intensity gradients of the image
 3. Apply non-maximum suppression to thin the resulting edges
 4. Apply double threshold to classify strong and weak edges
 5. Finalize edges by hysteresis: discard any weak edges which are not connected to a strong edge
- Shapes are represented by a random set of 50 points along the shape’s contours.

Step 2: Compute **Shape Context**
- For each of the 50 points, describe its position in relation to the 49 other points.
  Points are classified based on its *angle* and *logarithmic* distance to the given point. The resulting 12x5 matrix is called a *histogram*.
- The resulting list of 50 histograms accurately describes the given shape.

Step 3: Match Edges to Template
- For each template, create a matrix which finds the cost between all histograms.
  The cost is computed using the *Chi Squared Test Statistic*.

Step 4: Match Edges to Template (cont.)
- Optimally select one-to-one corresponding histograms which minimizes the net cost of matching. This essentially aligns the two shapes.
  This program uses the efficient Munkres/Hungarian Method to solve this linear assignments problem.

Step 5: Run Affine Transformations
- Given the set of correspondences, transform the input image to roughly align the shapes. This way, correct matches are closely aligned while others won’t converge.
- Repeat steps 2-4 with transformed shape.

Step 6: Select Template with lowest cost
- Get the matching distance for each template by adding a weighted sum of the shape distance (outputted by the Hungarian Method) and the transformation energy (how much transformation was required to align the shapes).
- Select prototype (character) using a nearest neighbor classifier (k-NN).
- Sort templates by its matching distance.
- For the first k (typically 3) templates, select the mode (most common) prototype.

---
### Thin Plate Spline Transformation
The name *thin plate spline* refers to a physical analogy involving the bending of a thin sheet of metal. Just as the metal has rigidity, the TPS fit resists bending also, implying a penalty involving the smoothness of the fitted surface.

In the physical setting, the deflection is in the z direction, orthogonal to the plane. In order to apply this idea to the problem of coordinate transformation, one interprets the lifting of the plate as a displacement of the x or y coordinates within the plane.

---
### Conclusion
My algorithm is capable of reading printed and handwritten text, assuming that the characters are spatially distinct from one another. It can also be trained with 3D objects using multiple views and to find visually-similar trademarks.

The biggest drawback to this method at the present moment is processing speed, specifically of the optimal assignments problem solver (Hungarian Method) which runs in O(N3) time (where N is the number of edge points). Converting the MATLAB code to C++ may improve the speed, but would still take significant time per character.

Accuracy can be significantly improved if *Regularized Thin Plate Spline* (TPS) transformations were used. TPS smoothly warps a set of points by selecting a tight match while minimizing the bending energy (a measure of how much transformation is needed to align the points). Affine transformations used in my algorithm are limited as it only allows for tilting of a rigid plane.

The construction of the algorithm gave me insight into the true complexity behind computer vision.

---
### Bibliography
1. Belongie, S., J. Malik, and J. Puzicha. "Shape Matching and Object Recognition Using Shape Contexts." IEEE Transactions on Pattern Analysis and Machine Intelligence 24.4 (2002): 509-22. Print.
2. Zhang, Min. "Shape Context Matching Theory." Firefly's Space. 21 Dec. 2012. Web.
3. "Shape Context." Wikipedia. Wikimedia Foundation. Web.
4. Wenying, Mo, and Zuchun, Ding. "A Digital Character Recognition Algorithm Based on the Template Weighted Match Degree." Print.
