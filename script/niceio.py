#!/usr/bin/env python
#coding: utf-8

'''
Created on 2015-07-15

@author snow
'''

import os
import sys
import Queue

reload(sys)
sys.setdefaultencoding('utf8')

class Reader(object):
    def read_queue(self, file_name):
        '''
        读取文件内容保存到队列中
        _queue = ['line1', 'line2', ...]
        '''
        _queue = Queue.Queue(maxsize = 30000)
        if not os.path.exists(file_name):
            return _queue
        with open(file_name, 'r') as fp_in:
            for line in fp_in:
                if line.startswith("#"):
                    continue
                item = line.rstrip().replace('\n','')
                if item != '':
                    _queue.put(item)
        return _queue

    def read_dict(self, file_name):
        '''
        读取文件内容保存到字典中
        _dict = {'line1' : 1, 'line2' : 1}
        '''
        _dict = dict()
        if not os.path.exists(file_name):
            return _dict
        with open(file_name, 'r') as fp_in:
            for line in fp_in:
                if line.startswith("#"):
                    continue
                _dict[line.rstrip().replace('\n','')] = 1
        return _dict

    def read_dict_col2(self, file_name, delimiter = '\t'):
        '''
        读取文件内容保存到字典中
        _dict = {'col2' : col1, 'col2' : col1}
        '''
        _dict = dict()
        if not os.path.exists(file_name):
            return _dict
        with open(file_name, 'r') as fp_in:
            for line in fp_in:
                line_list = line.rstrip().replace('\n', '').split(delimiter)
                if len(line_list) == 2:
                    _dict[line_list[1]] = line_list[0]
        return _dict

class Writer(object):

    def write_dict_keys(self, _dict, file_name):
        with open(file_name, 'w') as fp_out:
            for k in _dict.keys():
                fp_out.write(k + '\n')

    def write_queue(self, _queue, file_name):
        with open(file_name, 'w') as fp_out:
            while not _queue.empty():
                fp_out.write(_queue.get() + '\n')
