/************************************************************************
*
*  mexLap.c 
   modified by Liming from gsong, for mex functions in Matlab
   same interface as [C, T] = hungarian(A)
   as matlab is column-first memory allocation and C/C++ is row-first,
   here we use the rowsol as the output

*************************************************************************/
#include "mex.h"

/*************** CONSTANTS  *******************/

  #define BIG 100000

/*************** TYPES      *******************/

  typedef int row;
  typedef int col;
  typedef double cost;

/*************** FUNCTIONS  *******************/
cost lap(int dim, cost **assigncost, row *rowsol, col *colsol, cost *u, cost *v);


void mexFunction( int nlhs, mxArray *plhs[], 
		  int nrhs, const mxArray*prhs[] )
     
{ 
    unsigned int m,n, i; 
    mxArray *mxColsol, *mxRowsol, *mxU, *mxV, *mxSol; 
    
    double *inputcost, ** assigncost, *u, *v, lapcost, *sol;
    int *rowsol, *colsol, dim;
    
    
    /* Check for proper number of arguments */
    if (nrhs != 1) { 
	mexErrMsgTxt("One input arguments required."); 
    } else if (nlhs > 2) {
	mexErrMsgTxt("Too many output arguments."); 
    } 
    
    /* Check the dimensions of Y.  Y can be 4 X 1 or 1 X 4. */ 
    
    m = mxGetM(prhs[0]); 
    n = mxGetN(prhs[0]);
   
    if (!mxIsDouble(prhs[0]) 	|| m != n ) { 
        mexErrMsgTxt("The only input must be a square double matrix."); 
    } 
    
    inputcost = (double *) mxGetData(prhs[0]);
    
    
    /* initialize the c funcition input*/
         
    mxColsol = mxCreateNumericMatrix(1, n, mxINT32_CLASS, mxREAL);
    mxRowsol = mxCreateNumericMatrix(1, n, mxINT32_CLASS, mxREAL);
    mxU = mxCreateNumericMatrix(1, n, mxDOUBLE_CLASS, mxREAL);
    mxV = mxCreateNumericMatrix(1, n, mxDOUBLE_CLASS, mxREAL);
    
    
    /* get raw data pointer */ 
    colsol = (int *) mxGetData(mxColsol);
    rowsol = (int *) mxGetData(mxRowsol);
    u = (double *) mxGetData(mxU);
    v = (double *) mxGetData(mxV);
    
    /* fill in the input*/
    dim = n;
    assigncost = (double**) mxCalloc(n, sizeof(double*));
    for (i=0; i<n; i++)
    {
        assigncost[i] = inputcost + i*dim;
    }
    
    /*
      mexPrintf("\n");
    for (i = 0; i < dim; i++)
    {
      mexPrintf("\n");
      for (j = 0; j < dim; j++)
	mexPrintf("%4f ", inputcost[i * dim + j]);
    }
      mexPrintf("\n");


      mexPrintf("\n");
    for (i = 0; i < dim; i++)
    {
      mexPrintf("\n");
      for (j = 0; j < dim; j++)
	mexPrintf("%4f ", assigncost[i][j]);
    }
      mexPrintf("\n");
    */
    
    /* do real calculation*/
    lapcost = lap(dim, assigncost, rowsol, colsol, u, v); 
    /* lapcost = 1234; */
    
    /* fill in the output*/

    /* use double array for output */
    mxSol = mxCreateNumericMatrix(1, n, mxDOUBLE_CLASS, mxREAL);
    sol = (double*) mxGetData(mxSol);
    /*use 1-based index for matlab*/
    for (i=0; i<dim; i++) sol[i] = rowsol[i] + 1.0; 
    
    plhs[0] = mxSol;
    plhs[1] = mxCreateDoubleScalar(lapcost); 
    
    
    /* release memory*/
    mxFree(assigncost);
    mxDestroyArray(mxRowsol);
    mxDestroyArray(mxColsol);
    mxDestroyArray(mxU);
    mxDestroyArray(mxV);
    
        
    return;
    
}


/***********************************************/
cost lap(int dim, 
        cost **assigncost,
        col *rowsol, 
        row *colsol, 
        cost *u, 
        cost *v)

        /*
// input:
// dim        - problem size
// assigncost - cost matrix

// output:
// rowsol     - column assigned to row in solution
// colsol     - row assigned to column in solution
// u          - dual variables, row reduction numbers
// v          - dual variables, column reduction numbers
*/
{
  bool unassignedfound;
  row  i, imin, numfree = 0, prvnumfree, f, i0, k, freerow, *pred, *free_n;
  col  j, j1, j2, endofpath, last, low, up, *collist, *matches;
  cost min, h, umin, usubmin, v2, *d;
  cost lapcost = 0;
  /* AUGMENTING ROW REDUCTION */
  int loopcnt = 0;           /* do-loop to be done twice. */

  free_n    = (row *)malloc(dim*sizeof(row)); /* new row[dim];       // list of unassigned rows.*/
  collist = (col *)malloc(dim*sizeof(col)); /*new col[dim];    // list of columns to be scanned in various ways.*/
  matches = (col *)malloc(dim*sizeof(col)); /* new col[dim];    // counts how many times a row could be assigned.*/
  d = (cost *)malloc(dim*sizeof(cost)); /*new cost[dim];         // 'cost-distance' in augmenting path calculation.*/
  pred = (row *)malloc(dim*sizeof(row)); /*new row[dim];       // row-predecessor of column in augmenting/alternating path.*/

  /* init how many times a row will be assigned in the column reduction. */
  for (i = 0; i < dim; i++)  
    matches[i] = 0;

  /* COLUMN REDUCTION  */
  for (j = dim-1; j >= 0; j--)    /*  reverse order gives better results. */
  {
    /* find minimum cost over rows. */
    min = assigncost[0][j]; 
    imin = 0;
    for (i = 1; i < dim; i++)  
      if (assigncost[i][j] < min) 
      { 
        min = assigncost[i][j]; 
        imin = i;
      }
    v[j] = min; 

    if (++matches[imin] == 1) 
    { 
      /* init assignment if minimum row assigned for first time. */
      rowsol[imin] = j; 
      colsol[j] = imin; 
    }
    else
      colsol[j] = -1;        /* row already assigned, column not assigned. */
  }

  /* REDUCTION TRANSFER */
  for (i = 0; i < dim; i++) 
    if (matches[i] == 0)     /* fill list of unassigned 'free_n' rows. */
      free_n[numfree++] = i;
    else
      if (matches[i] == 1)   /* transfer reduction from rows that are assigned once. */
      {
        j1 = rowsol[i]; 
        min = BIG;
        for (j = 0; j < dim; j++)  
          if (j != j1)
            if (assigncost[i][j] - v[j] < min) 
              min = assigncost[i][j] - v[j];
        v[j1] = v[j1] - min;
      }

  
  do
  {
    loopcnt++;

    /* scan all free rows.
     * in some cases, a free row may be replaced with another one to be scanned next.
     */
    k = 0; 
    prvnumfree = numfree; 
    numfree = 0; /* start list of rows still free after augmenting row reduction. */
    while (k < prvnumfree)
    {
      i = free_n[k]; 
      k++;

      /* find minimum and second minimum reduced cost over columns. */
      umin = assigncost[i][0] - v[0]; 
      j1 = 0; 
      usubmin = BIG;
      for (j = 1; j < dim; j++) 
      {
        h = assigncost[i][j] - v[j];
        if (h < usubmin)
          if (h >= umin) 
          { 
            usubmin = h; 
            j2 = j;
          }
          else 
          { 
            usubmin = umin; 
            umin = h; 
            j2 = j1; 
            j1 = j;
          }
      }

      i0 = colsol[j1];
      if (umin < usubmin) 
        /* change the reduction of the minimum column to increase the minimum
         * reduced cost in the row to the subminimum.
         */
        v[j1] = v[j1] - (usubmin - umin);
      else                   /* minimum and subminimum equal.*/
        if (i0 >= 0)         /* minimum column j1 is assigned.*/
        { 
          /* swap columns j1 and j2, as j2 may be unassigned. */
          j1 = j2; 
          i0 = colsol[j2];
        }

      /* (re-)assign i to j1, possibly de-assigning an i0. */
      rowsol[i] = j1; 
      colsol[j1] = i;

      if (i0 >= 0)           /* minimum column j1 assigned earlier. */
        if (umin < usubmin) 
          /* put in current k, and go back to that k.
           * continue augmenting path i - j1 with i0.
           */
          free_n[--k] = i0; 
        else 
          /* no further augmenting reduction possible.
           * store i0 in list of free rows for next phase.
           */
          free_n[numfree++] = i0; 
    }
  }
  while (loopcnt < 2);       /* repeat once. */

  /* AUGMENT SOLUTION for each free row. */
  for (f = 0; f < numfree; f++) 
  {
    freerow = free_n[f];       /* start row of augmenting path. */

    /* Dijkstra shortest path algorithm. */
    /* runs until unassigned column added to shortest path tree. */
    for (j = 0; j < dim; j++)  
    { 
      d[j] = assigncost[freerow][j] - v[j]; 
      pred[j] = freerow;
      collist[j] = j;        /* init column list. */
    }

    low = 0; /* columns in 0..low-1 are ready, now none. */
    up = 0;  /* columns in low..up-1 are to be scanned for current minimum, now none.
              * columns in up..dim-1 are to be considered later to find new minimum, 
              * at this stage the list simply contains all columns 
              */
    unassignedfound = false;
    do
    {
      if (up == low)         /* no more columns to be scanned for current minimum.*/
      {
        last = low - 1; 

        /* scan columns for up..dim-1 to find all indices for which new minimum occurs.
         * store these indices between low..up-1 (increasing up). 
         */
        min = d[collist[up++]]; 
        for (k = up; k < dim; k++) 
        {
          j = collist[k]; 
          h = d[j];
          if (h <= min)
          {
            if (h < min)     /* new minimum. */
            { 
              up = low;      /* restart list at index low. */
              min = h;
            }
            /* new index with same minimum, put on undex up, and extend list. */
            collist[k] = collist[up]; 
            collist[up++] = j; 
          }
        }

        /* check if any of the minimum columns happens to be unassigned.
         * if so, we have an augmenting path right away.
         */
        for (k = low; k < up; k++) 
          if (colsol[collist[k]] < 0) 
          {
            endofpath = collist[k];
            unassignedfound = true;
            break;
          }
      }

      if (!unassignedfound) 
      {
        /* update 'distances' between freerow and all unscanned columns, via next scanned column. */
        j1 = collist[low]; 
        low++; 
        i = colsol[j1]; 
        h = assigncost[i][j1] - v[j1] - min;

        for (k = up; k < dim; k++) 
        {
          j = collist[k]; 
          v2 = assigncost[i][j] - v[j] - h;
          if (v2 < d[j])
          {
            pred[j] = i;
            if (v2 == min)   /* new column found at same minimum value */
              if (colsol[j] < 0) 
              {
                /* if unassigned, shortest augmenting path is complete. */
                endofpath = j;
                unassignedfound = true;
                break;
              }
              /* else add to list to be scanned right away. */
              else 
              { 
                collist[k] = collist[up]; 
                collist[up++] = j; 
              }
            d[j] = v2;
          }
        }
      } 
    }
    while (!unassignedfound);

    /* update column prices. */
    for (k = 0; k <= last; k++)  
    { 
      j1 = collist[k]; 
      v[j1] = v[j1] + d[j1] - min;
    }

    /* reset row and column assignments along the alternating path. */
    do
    {
      i = pred[endofpath]; 
      colsol[endofpath] = i; 
      j1 = endofpath; 
      endofpath = rowsol[i]; 
      rowsol[i] = j1;
    }
    while (i != freerow);
  }

  /* calculate optimal cost. */
  
  for (i = 0; i < dim; i++)  
  {
    j = rowsol[i];
    u[i] = assigncost[i][j] - v[j];
    lapcost = lapcost + assigncost[i][j]; 
  }

  /* free reserved memory.
  delete[] pred;
  delete[] free_n;
  delete[] collist;
  delete[] matches;
  delete[] d;
    */
  free(pred);
  free(free_n);
  free(collist);
  free(matches);
  free(d);
  return lapcost;
}
